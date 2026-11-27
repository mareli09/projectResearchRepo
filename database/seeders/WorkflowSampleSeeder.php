<?php

namespace Database\Seeders;

use App\Domain\Repository\ResearchTypes;
use App\Domain\Site\Corpus;
use App\Domain\Site\DesignData;
use App\Models\AcademicUnit;
use App\Models\AccessRequest;
use App\Models\Cluster;
use App\Models\ClusterRun;
use App\Models\LlmSuggestion;
use App\Models\Program;
use App\Models\RecordFile;
use App\Models\ResearchCluster;
use App\Models\ResearchRecord;
use App\Models\ScreeningDecision;
use App\Models\SearchLog;
use App\Models\SimilarityCheck;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Loads the sample workflow data the design screens show (DesignData) into the real tables:
 * submissions with members, reviews and screening results; full-paper requests; clustering runs
 * with clusters and record assignments; theme-label suggestions; in-app notifications; search logs.
 * Also creates the sample people as accounts (password Password123).
 * SAMPLE DATA ONLY: local and evaluation copies, never production (CLAUDE.md §6, §17).
 */
class WorkflowSampleSeeder extends Seeder
{
    private const PASSWORD = 'Password123';

    /** Sample people who appear in the workflow data (fictional, from the prototype). */
    private const PEOPLE = [
        ['Enzo Navarro', User::ROLE_STUDENT], ['Francine Garcia', User::ROLE_STUDENT], ['Gabriel Ilagan', User::ROLE_STUDENT],
        ['Isaac Tolentino', User::ROLE_STUDENT], ['Patricia Soriano', User::ROLE_STUDENT], ['Nicole Hernandez', User::ROLE_STUDENT],
        ['Carlo Mendoza', User::ROLE_STUDENT], ['Leah Santos', User::ROLE_STUDENT], ['Mika Pascual', User::ROLE_STUDENT],
        ['Dr. Evelyn Sarmiento', User::ROLE_ADVISER], ['Engr. Marites Lualhati', User::ROLE_ADVISER], ['Prof. Dennis Galang', User::ROLE_ADVISER],
    ];

    /** @var array<string, User> name => user */
    private array $users = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('WorkflowSampleSeeder skipped in production.');

            return;
        }
        if (Submission::exists()) {
            $this->command?->warn('Workflow data already exists. WorkflowSampleSeeder skipped.');

            return;
        }

        DB::transaction(function (): void {
            $this->people();
            $this->submissions();
            $this->accessRequests();
            $this->clustering();
            $this->notifications();
            $this->searchLogs();
        });
    }

    private function people(): void
    {
        $ceis = AcademicUnit::where('code', 'CEIS')->firstOrFail();
        foreach (User::all() as $u) {
            $this->users[$u->name] = $u;
        }

        foreach (self::PEOPLE as [$name, $role]) {
            if (isset($this->users[$name])) {
                continue;
            }
            $local = Str::of(preg_replace('/^(Dr|Prof|Engr)\.\s+/', '', $name))->lower()->replace(' ', '.')->toString();
            $user = new User([
                'name' => $name,
                'email' => $local.'@'.config('tua.email_domain'),
                'password' => self::PASSWORD,
                'user_type' => $role === User::ROLE_STUDENT ? 'student' : 'faculty',
                'academic_unit_id' => $ceis->id,
            ]);
            $user->is_active = true;
            $user->email_verified_at = now();
            $user->save();
            $user->assignRole($role);
            $this->users[$name] = $user;
        }
    }

    private function user(string $name): ?User
    {
        return $this->users[$name] ?? null;
    }

    private function submissions(): void
    {
        $cluster = ResearchCluster::where('number', 1)->first();
        $ceis = AcademicUnit::where('code', 'CEIS')->first();
        $programs = Program::pluck('id', 'name');
        $coordinator = User::role(User::ROLE_COORDINATOR)->first();
        $decisions = ['Submitted' => 'submit', 'Endorsed' => 'endorse', 'Reviewed' => 'clear', 'Published' => 'approve', 'Returned' => 'return', 'Rejected' => 'reject'];
        $placeholders = RecordFile::where('path', 'like', '\_samples/%')->get()->unique('slot')
            ->mapWithKeys(fn (RecordFile $f) => [$f->slot => $f->only('path', 'original_name', 'mime', 'size', 'sha256')])->all();

        foreach (DesignData::submissions() as $s) {
            $score = (float) $s['score'];
            $flag = $score >= config('tua.screening.duplicate_threshold') ? 'probable_duplicate' : ($score >= config('tua.screening.review_threshold') ? 'similar' : 'original');
            $first = Carbon::parse(preg_replace('/ · .*$/', '', $s['history'][0][2]));

            $sub = Submission::create([
                'public_id' => $s['id'],
                'student_user_id' => $this->user($s['student'])?->id ?? $this->users['Jasmine Villanueva']->id,
                'adviser_user_id' => $this->user($s['adviser'])?->id,
                'research_cluster_id' => $cluster?->id,
                'academic_unit_id' => $ceis?->id,
                'program_id' => $programs[$s['program']] ?? null,
                'title' => $s['title'],
                'abstract' => $s['abstract'],
                'research_type' => ResearchTypes::typeKey($s['type']) ?? 'undergraduate_thesis',
                'year' => 2026,
                'keywords' => $s['keywords'],
                'sdgs' => $s['sdg'],
                'status' => strtolower($s['status']),
                'current_step' => $s['step'],
                'max_similarity' => $score,
                'flag' => $flag,
                'submitted_at' => $first,
            ]);

            // The five files: the shared sample placeholders (published ones already moved to their record below).
            foreach ($placeholders as $slot => $f) {
                RecordFile::create($f + ['submission_id' => $sub->id, 'slot' => $slot, 'is_current' => true, 'uploaded_by' => $sub->student_user_id]);
            }

            foreach ($s['members'] as $i => $m) {
                $sub->members()->create(['name' => $m, 'user_id' => $this->user($m)?->id, 'position' => $i + 1]);
            }
            foreach ($s['history'] as $i => [$event, $who, $when, $reason]) {
                $sub->reviews()->create([
                    'step' => $i + 1,
                    'reviewer_user_id' => $this->user($who)?->id,
                    'decision' => $decisions[$event] ?? 'submit',
                    'reason' => $reason ?: null,
                    'created_at' => Carbon::parse(preg_replace('/ · .*$/', '', $when)),
                ]);
            }

            // Screening result: top 5 matches (sample scores until Sprint 4).
            $check = SimilarityCheck::create(['submission_id' => $sub->id, 'run_at' => $first, 'vectorizer_version' => 'sample', 'top_score' => $score, 'flag' => $flag]);
            foreach (DesignData::matches($s) as $rank => $m) {
                $check->matches()->create([
                    'record_id' => $m['record']['id'],
                    'score' => $m['score'],
                    'rank' => $rank + 1,
                    'shared_terms' => array_values(array_intersect($m['record']['keywords'], $s['keywords'])),
                ]);
            }

            if (in_array($s['status'], ['Reviewed', 'Published'], true)) {
                ScreeningDecision::create([
                    'submission_id' => $sub->id,
                    'coordinator_user_id' => $coordinator?->id,
                    'decision' => 'clear',
                    'reason' => collect($s['history'])->firstWhere(0, 'Reviewed')[3] ?? null,
                    'label_for_calibration' => 'distinct',
                ]);
            }
        }
    }

    private function accessRequests(): void
    {
        $records = ResearchRecord::pluck('id', 'public_id');
        $urdc = User::role(User::ROLE_URDC)->first();

        foreach (DesignData::accessRequests() as $a) {
            $user = $this->user($a['who']);
            if ($user === null || ! isset($records[$a['rec']])) {
                continue;
            }
            $date = Carbon::parse($a['date']);
            AccessRequest::create([
                'public_id' => $a['id'],
                'user_id' => $user->id,
                'record_id' => $records[$a['rec']],
                'purpose' => $a['purpose'],
                'status' => strtolower($a['status']),
                'decided_by' => $a['status'] === 'Pending' ? null : $urdc?->id,
                'reason' => $a['status'] === 'Declined' ? 'Not covered by the current access policy.' : null,
                'decided_at' => $a['status'] === 'Pending' ? null : $date->copy()->addDay(),
                'expires_at' => $a['status'] === 'Approved' ? $date->copy()->addDays(15) : null,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }

    /** Three runs; RUN-06 is current, with the design themes as its 7 clusters and every published record assigned. */
    private function clustering(): void
    {
        $urdc = User::role(User::ROLE_URDC)->first();
        $coordinator = User::role(User::ROLE_COORDINATOR)->first();
        $corpus = Corpus::records();
        $labels = DesignData::labels();

        foreach (array_reverse(DesignData::runs()) as $r) {
            $run = ClusterRun::create([
                'public_id' => $r['id'],
                'k' => $r['k'],
                'silhouette' => $r['sil'],
                'davies_bouldin' => $r['db'],
                'n_records' => $r['n'],
                'params' => ['init' => 'k-means++', 'n_init' => 10, 'random_state' => 42, 'k_min' => 2, 'k_max' => 15, 'sample' => true],
                'triggered_by' => str_starts_with($r['by'], 'Scheduler') ? null : $urdc?->id,
                'trigger_label' => $r['by'],
                'state' => $r['state'] === 'Current' ? 'current' : 'archived',
                'finished_at' => Carbon::parse($r['date'])->setTime(2, 20),
            ]);

            if ($r['state'] !== 'Current') {
                continue;
            }

            foreach (Corpus::THEMES as $n => $t) {
                $rows = $corpus->where('theme', $n);
                $l = $labels[$n];
                $cluster = Cluster::create([
                    'cluster_run_id' => $run->id,
                    'number' => $n,
                    'top_terms' => $t['terms'],
                    'label' => $l['label'],
                    'label_status' => $l['confirmed'] ? 'confirmed' : 'pending',
                    'size' => $rows->count(),
                    'x' => $t['center'][0],
                    'y' => $t['center'][1],
                ]);
                foreach ($rows->chunk(500) as $chunk) {
                    DB::table('record_cluster')->insert($chunk->map(fn ($rec) => [
                        'record_id' => $rec['id'], 'cluster_id' => $cluster->id, 'x' => $rec['x'], 'y' => $rec['y'],
                    ])->values()->all());
                }

                LlmSuggestion::create([
                    'kind' => 'theme_label',
                    'subject_type' => $cluster->getMorphClass(),
                    'subject_id' => $cluster->id,
                    'suggested' => $l['llm'],
                    'final' => $l['confirmed'] ? $l['label'] : null,
                    'action' => $l['action'],
                    'rating' => $l['rating'] ?: null,
                    'provider' => 'sample',
                    'latency_ms' => 1200,
                    'fallback_used' => false,
                    'acted_by' => $l['confirmed'] ? $coordinator?->id : null,
                    'acted_at' => $l['confirmed'] ? $run->finished_at?->copy()->addDays(2) : null,
                ]);
            }
        }
    }

    /** In-app copies of the workflow emails for the demo users (database notifications). */
    private function notifications(): void
    {
        $byRole = [
            'student' => User::role(User::ROLE_STUDENT)->where('email', 'jasmine.villanueva@'.config('tua.email_domain'))->first(),
            'adviser' => User::role(User::ROLE_ADVISER)->where('email', 'ramon.castillo@'.config('tua.email_domain'))->first(),
            'coordinator' => User::role(User::ROLE_COORDINATOR)->first(),
            'urdc' => User::role(User::ROLE_URDC)->first(),
        ];

        foreach ($byRole as $role => $user) {
            if ($user === null) {
                continue;
            }
            foreach (DesignData::notifications($role) as $n) {
                $at = Carbon::parse(str_replace(' · ', ' ', $n['t']));
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'type' => 'App\\Notifications\\WorkflowNotice',
                    'notifiable_type' => $user->getMorphClass(),
                    'notifiable_id' => $user->id,
                    'data' => json_encode(['subject' => $n['subj'], 'body' => $n['body']]),
                    'read_at' => $n['read'] ? $at : null,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }
        }
    }

    /** Searches with no or few results, for the Table 7 gaps visual. */
    private function searchLogs(): void
    {
        $student = $this->users['Jasmine Villanueva'] ?? null;
        $rows = [];
        foreach (DesignData::searchGaps() as [$query, $times, $results, $last]) {
            $day = Carbon::parse($last.', 2026');
            for ($i = 0; $i < $times; $i++) {
                $rows[] = ['user_id' => $student?->id, 'query' => $query, 'filters' => null, 'results_count' => $results, 'created_at' => $day->copy()->subDays($i % 20)];
            }
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            SearchLog::insert($chunk);
        }
    }
}
