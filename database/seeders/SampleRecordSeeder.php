<?php

namespace Database\Seeders;

use App\Models\AcademicUnit;
use App\Models\Program;
use App\Models\ResearchRecord;
use App\Models\User;
use Database\Seeders\Support\SampleDocuments;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 1,419 sample records (CLAUDE.md §6 Seeders), ported from the prototype's generator:
 * same seed, college and year weights, themes and college-theme affinities.
 * 1,414 generated records + 5 planted near-duplicates for the screening demo = 1,419.
 * About 82% have all files; the files are shared placeholder documents under research/_samples.
 * SAMPLE DATA ONLY. Never run in production; never real student names in the evaluation copy.
 */
class SampleRecordSeeder extends Seeder
{
    public const TOTAL = 1419;

    private const GENERATED = self::TOTAL - 5;

    private const COLLEGE_WEIGHTS = ['CAHS' => 14, 'CASE' => 20, 'IBAM' => 18, 'CEIS' => 22, 'CMT' => 12, 'SLCN' => 14];

    private const YEAR_WEIGHTS = [2015 => 60, 2016 => 70, 2017 => 80, 2018 => 95, 2019 => 110, 2020 => 90, 2021 => 115, 2022 => 140, 2023 => 170, 2024 => 200, 2025 => 240];

    private const TYPES = ['undergraduate_thesis', 'capstone_project', 'graduate_thesis', 'faculty_research'];

    private const PREFIX = ['', 'A Study on ', 'An Assessment of ', 'Evaluating '];

    /** Theme => college affinity, in COLLEGE_WEIGHTS order (CAHS, CASE, IBAM, CEIS, CMT, SLCN). */
    private const AFF = [1 => [5, 1, 1, 4, 2, 3], 2 => [2, 1, 0, 0, 1, 9], 3 => [1, 2, 1, 6, 1, 0], 4 => [1, 1, 2, 9, 0, 0], 5 => [0, 1, 9, 1, 0, 0], 6 => [1, 9, 1, 1, 0, 1], 7 => [3, 2, 0, 0, 9, 1]];

    private const THEMES = [
        1 => ['terms' => ['health', 'patient', 'telemedicine', 'record', 'clinic'], 'sdg' => [3, 9],
            'subj' => ['Electronic Health Record Adoption', 'Telemedicine Readiness', 'Patient Queue Management', 'Health Information Privacy Awareness', 'Mobile Medication Reminders', 'Online Appointment Scheduling', 'Digital Prescription Tracking'],
            'ctx' => ['among Barangay Health Centers in Quezon City', 'in a Tertiary Hospital in Metro Manila', 'for Outpatient Clinics', 'among Senior Citizens', 'in Private Clinics in Cubao', 'among Rural Health Units', 'for Pharmacy Services']],
        2 => ['terms' => ['nursing', 'care', 'community', 'maternal', 'wellbeing'], 'sdg' => [3, 5],
            'subj' => ['Maternal Health Practices', 'Burnout and Coping Strategies', 'Hand Hygiene Compliance', 'Breastfeeding Self-Efficacy', 'Fall Prevention Practices', 'Mental Health Literacy', 'Post-Operative Pain Management'],
            'ctx' => ['among Staff Nurses in Quezon City', 'of First-Time Mothers', 'in Community Health Settings', 'among Student Nurses', 'in Medical-Surgical Wards', 'of Family Caregivers', 'among Elderly Residents']],
        3 => ['terms' => ['machine', 'learning', 'prediction', 'classification', 'algorithm'], 'sdg' => [9, 4],
            'subj' => ['Student Dropout Prediction', 'Sentiment Classification of Course Evaluations', 'Crop Disease Detection', 'Flood Risk Prediction', 'Document Classification', 'Fake News Detection', 'Enrollment Forecasting'],
            'ctx' => ['Using Random Forest', 'Using Naive Bayes', 'Using Convolutional Neural Networks', 'Using Decision Trees', 'Using K-Means Clustering', 'Using Support Vector Machines', 'Using Logistic Regression']],
        4 => ['terms' => ['system', 'web', 'mobile', 'management', 'application'], 'sdg' => [9, 16],
            'subj' => ['Web-Based Inventory Management System', 'Mobile Attendance Monitoring Application', 'Online Clearance Processing System', 'Barangay Records Management System', 'Library Reservation System', 'Event Ticketing Platform', 'Alumni Tracer System'],
            'ctx' => ['for a University in Quezon City', 'for Small Retail Stores', 'with QR Code Integration', 'for Local Government Units', 'with SMS Notification', 'for Student Organizations', 'with Role-Based Access']],
        5 => ['terms' => ['business', 'marketing', 'consumer', 'msme', 'financial'], 'sdg' => [8, 12],
            'subj' => ['Social Media Marketing Effectiveness', 'Consumer Buying Behavior', 'Financial Literacy', 'Digital Payment Adoption', 'Customer Satisfaction', 'Employee Retention Factors', 'Food Service Quality'],
            'ctx' => ['among MSMEs in Cubao', 'of Generation Z Consumers', 'among Online Sellers', 'in Fast-Food Chains', 'among Hotel Employees', 'of Milk Tea Shops', 'among Sari-Sari Store Owners']],
        6 => ['terms' => ['learning', 'students', 'teaching', 'education', 'academic'], 'sdg' => [4, 5],
            'subj' => ['Blended Learning Effectiveness', 'Reading Comprehension Strategies', 'Academic Motivation', 'Teacher Self-Efficacy', 'Gamified Instruction', 'Study Habits and Performance', 'Inclusive Classroom Practices'],
            'ctx' => ['among Senior High School Students', 'in Public Elementary Schools', 'of College Freshmen', 'among Pre-Service Teachers', 'in Science Classes', 'during Flexible Learning', 'in Private Schools in Quezon City']],
        7 => ['terms' => ['laboratory', 'specimen', 'diagnostic', 'bacterial', 'screening'], 'sdg' => [3, 6],
            'subj' => ['Antibacterial Activity of Plant Extracts', 'Water Quality Assessment', 'Prevalence of Intestinal Parasites', 'Blood Glucose Screening', 'Antibiotic Resistance Patterns', 'Specimen Rejection Rates', 'Dengue Seroprevalence'],
            'ctx' => ['in Quezon City', 'in Clinical Laboratories', 'among School-Age Children', 'in Tertiary Hospitals', 'in Selected Barangays', 'in Drinking Water Stations', 'among Food Handlers']],
    ];

    private const FIRST = ['Andrea', 'Bea', 'Carlo', 'Danica', 'Enzo', 'Francine', 'Gabriel', 'Hannah', 'Isaac', 'Janelle', 'Kevin', 'Leah', 'Miguel', 'Nicole', 'Oscar', 'Patricia', 'Rafael', 'Sofia', 'Tristan', 'Unice', 'Vince', 'Wendy', 'Xandra', 'Yuri', 'Zoe', 'Joshua', 'Katrina', 'Lance', 'Mika', 'Paolo'];

    private const LAST = ['Aquino', 'Bautista', 'Cruz', 'Dela Rosa', 'Espiritu', 'Fernandez', 'Garcia', 'Hernandez', 'Ilagan', 'Javier', 'Lim', 'Mendoza', 'Navarro', 'Ocampo', 'Pascual', 'Quiambao', 'Ramos', 'Santos', 'Tolentino', 'Uy', 'Villareal', 'Yap', 'Zamora', 'Manalastas', 'Soriano'];

    private const ADVISERS = [
        'CAHS' => ['Dr. Regina Alcantara', 'Prof. Marvin Delos Santos', 'Prof. Cecilia Tan'],
        'CASE' => ['Dr. Lorna Bernardo', 'Prof. Jericho Malonzo', 'Dr. Aileen Pangan'],
        'IBAM' => ['Dr. Hector Lacson', 'Prof. Grace Dimaculangan', 'Prof. Nestor Cabrera'],
        'CEIS' => ['Prof. Ramon Castillo', 'Dr. Evelyn Sarmiento', 'Prof. Dennis Galang', 'Engr. Marites Lualhati'],
        'CMT' => ['Dr. Rosario Villamor', 'Prof. Allan Peralta'],
        'SLCN' => ['Dr. Imelda Tuazon', 'Prof. Corazon Abad', 'Prof. Ruel Mangubat'],
    ];

    private const SLOT_FILES = [
        'abstract' => ['abstract.docx', 'Sample abstract (URDC format).docx'],
        'full_paper' => ['full_paper.pdf', 'Sample full paper.pdf'],
        'imrad' => ['imrad.pdf', 'Sample IMRAD version.pdf'],
        'toa' => ['toa.pdf', 'Sample Transfer of Ownership Agreement.pdf'],
        'references' => ['references.docx', 'Sample list of references.docx'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('SampleRecordSeeder skipped in production.');

            return;
        }
        if (ResearchRecord::withTrashed()->exists()) {
            $this->command?->warn('Records already exist. SampleRecordSeeder skipped.');

            return;
        }

        $this->state = 20261119; // same seed as the prototype
        $records = $this->generate();
        $this->insert($records);
    }

    /** PRNG state. Own generator, so other code using mt_rand()/Faker cannot change the sample data. */
    private int $state = 0;

    /** mulberry32, the prototype's generator, in unsigned 32-bit arithmetic. Returns [0, 1). */
    private function rnd(): float
    {
        $m = 0xFFFFFFFF;
        $this->state = ($this->state + 0x6D2B79F5) & $m;
        $a = $this->state;
        $t = self::imul($a ^ ($a >> 15), 1 | $a);
        $t = (($t + self::imul($t ^ ($t >> 7), 61 | $t)) & $m) ^ $t;

        return (($t ^ ($t >> 14)) & $m) / 4294967296;
    }

    /** JavaScript Math.imul on unsigned 32-bit values. */
    private static function imul(int $a, int $b): int
    {
        $a &= 0xFFFFFFFF;
        $b &= 0xFFFFFFFF;
        $low = ($a & 0xFFFF) * $b;
        $high = ((($a >> 16) & 0xFFFF) * $b) & 0xFFFF;

        return ($low + ($high << 16)) & 0xFFFFFFFF;
    }

    /**
     * @template T
     *
     * @param  array<int|string, T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        $values = array_values($items);

        return $values[(int) floor($this->rnd() * count($values))];
    }

    /**
     * @param  array<int|string, int|float>  $weights  key => weight
     */
    private function wpick(array $weights): int|string
    {
        $r = $this->rnd() * array_sum($weights);
        foreach ($weights as $key => $w) {
            $r -= $w;
            if ($r <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    /**
     * @param  list<mixed>  $a
     * @return list<mixed>
     */
    private function shuffle(array $a): array
    {
        for ($i = count($a) - 1; $i > 0; $i--) {
            $j = (int) floor($this->rnd() * ($i + 1));
            [$a[$i], $a[$j]] = [$a[$j], $a[$i]];
        }

        return $a;
    }

    /** @return list<array<string, mixed>> */
    private function generate(): array
    {
        $programs = [];
        foreach (Program::with('academicUnit')->get() as $p) {
            $programs[$p->academicUnit->code][] = $p->name;
        }

        $combos = [];
        foreach (self::THEMES as $id => $t) {
            $c = [];
            foreach (self::PREFIX as $p) {
                foreach ($t['subj'] as $s) {
                    foreach ($t['ctx'] as $x) {
                        $c[] = [$p, $s, $x];
                    }
                }
            }
            $combos[$id] = $this->shuffle($c);
        }

        $codes = array_keys(self::COLLEGE_WEIGHTS);
        $usedTitles = [];
        $out = [];

        for ($i = 1; $i <= self::GENERATED; $i++) {
            $code = (string) $this->wpick(self::COLLEGE_WEIGHTS);
            $ci = array_search($code, $codes, true);
            $themeId = (int) $this->wpick(array_map(fn (array $aff) => $aff[$ci] + 0.15, self::AFF));
            $theme = self::THEMES[$themeId];

            [$p, $s, $x] = array_pop($combos[$themeId]) ?? [$this->pick(self::PREFIX), $this->pick($theme['subj']), $this->pick($theme['ctx'])];
            if (preg_match('/Assessment|Evaluation/', $s)) {
                $p = '';
            }
            $title = $p.$s.' '.$x;
            // Keep sample titles unique so later duplicate checks start from a clean repository.
            foreach ([': A Follow-Up Study', ': A Comparative Study', ' (Second Cycle)', ': A Replication Study'] as $suffix) {
                if (! isset($usedTitles[strtolower($title)])) {
                    break;
                }
                $title = $p.$s.' '.$x.$suffix;
            }
            $usedTitles[strtolower($title)] = true;

            $year = (int) $this->wpick(self::YEAR_WEIGHTS);
            $type = self::TYPES[(int) $this->wpick($code === 'CEIS' ? [30, 50, 8, 12] : [62, 4, 16, 18])];

            $terms = $this->shuffle($theme['terms']);
            $keywords = array_slice($terms, 0, 2 + (int) floor($this->rnd() * 3));
            $long = array_values(array_filter(explode(' ', strtolower($s)), fn ($w) => strlen($w) > 5));
            if ($long !== []) {
                $keywords[] = trim($long[0], ',');
            }
            $keywords = array_values(array_unique($keywords));
            foreach ($terms as $t) { // at least 3 (CLAUDE.md §7 M2)
                if (count($keywords) >= 3) {
                    break;
                }
                if (! in_array($t, $keywords, true)) {
                    $keywords[] = $t;
                }
            }
            $keywords = array_slice($keywords, 0, 5);

            $program = $this->pick($programs[$code]);
            $degree = match (true) {
                $type === 'graduate_thesis' || str_starts_with($program, 'Master') => 'graduate',
                $type === 'faculty_research' => 'faculty',
                default => 'undergraduate',
            };

            $authors = [];
            $n = 1 + (int) floor($this->rnd() * 4);
            for ($a = 0; $a < $n; $a++) {
                $authors[] = $this->pick(self::FIRST).' '.$this->pick(self::LAST);
            }

            $sdgs = array_slice($this->shuffle($theme['sdg']), 0, 1 + (int) floor($this->rnd() * 2));

            $out[] = [
                'public_id' => sprintf('TUA-%d-%04d', $year, $i),
                'title' => $title,
                'abstract' => $this->abstract($type, $title, $theme['terms']),
                'year' => $year,
                'research_type' => $type,
                'degree_level' => $degree,
                'college' => $code,
                'program' => $program,
                'adviser' => $this->pick(self::ADVISERS[$code]),
                'authors' => $authors,
                'keywords' => $keywords,
                'sdgs' => $sdgs,
                'views' => (int) floor($this->rnd() * $this->rnd() * 900) + 5,
                'downloads' => (int) floor($this->rnd() * $this->rnd() * 240),
                'complete' => $this->rnd() < 0.82,
                'imrad_na' => $this->rnd() < 0.35,
            ];
        }

        return array_merge($out, $this->planted());
    }

    /** @param  list<string>  $terms */
    private function abstract(string $type, string $title, array $terms): string
    {
        $label = strtolower(str_replace('_', ' ', $type));
        $topic = strtolower((string) preg_replace('/^(A Study on |An Assessment of |Evaluating )/', '', $title));
        $design = $this->pick(['descriptive-correlational', 'quasi-experimental', 'mixed-methods', 'developmental', 'descriptive survey']);
        $n = 20 + (int) floor($this->rnd() * 280);
        $finding = $this->pick(['a significant relationship', 'a moderate improvement', 'high acceptability ratings', 'notable differences across groups']);
        $rec = $this->pick(['wider adoption in partner institutions', 'follow-up studies with larger samples', 'integration into existing services', 'policy review by the concerned offices']);

        return "This {$label} examined {$topic}. Using a {$design} design with {$n} respondents, the study looked at "
            .implode(', ', array_slice($terms, 0, 3))." and related factors. Results showed {$finding} and the study recommends {$rec}.";
    }

    /** Near-duplicates of the demo submission SUB-0142, for the screening demo (Sprint 4). @return list<array<string, mixed>> */
    private function planted(): array
    {
        $base = ['college' => 'CEIS', 'degree_level' => 'undergraduate', 'complete' => true, 'imrad_na' => false];

        return [
            $base + ['public_id' => 'TUA-2023-1420', 'title' => 'Capstone Proposal Title Similarity Checker Using TF-IDF and Cosine Similarity', 'program' => 'BS Information Technology', 'year' => 2023, 'research_type' => 'capstone_project', 'adviser' => 'Dr. Evelyn Sarmiento', 'authors' => ['Mika Santos', 'Lance Uy', 'Danica Ocampo'], 'keywords' => ['tf-idf', 'cosine similarity', 'capstone', 'title screening'], 'sdgs' => [9, 4], 'views' => 412, 'downloads' => 96,
                'abstract' => 'This capstone project developed a checker that compares proposed capstone titles and abstracts with approved capstone proposals of the department. Proposals are converted into TF-IDF vectors and ranked by cosine similarity, and the most similar titles are shown to the panel before title defense.'],
            $base + ['public_id' => 'TUA-2022-1421', 'title' => 'Detecting Duplicate Research Titles in a University Library Using Text Similarity', 'program' => 'BS Computer Science', 'year' => 2022, 'research_type' => 'undergraduate_thesis', 'adviser' => 'Prof. Dennis Galang', 'authors' => ['Kevin Ramos', 'Bea Javier'], 'keywords' => ['duplicate detection', 'text similarity', 'research titles', 'library'], 'sdgs' => [9], 'views' => 288, 'downloads' => 51,
                'abstract' => 'The study detected duplicate research titles in the university library catalog. Titles were preprocessed, compared with string and text similarity measures, and duplicates were reported to the librarian for review.'],
            $base + ['public_id' => 'TUA-2024-1422', 'title' => 'Web-Based Thesis Archive with Keyword Search for the College of Engineering and Information Sciences', 'program' => 'BS Information Technology', 'year' => 2024, 'research_type' => 'capstone_project', 'adviser' => 'Prof. Ramon Castillo', 'authors' => ['Sofia Lim', 'Tristan Pascual'], 'keywords' => ['thesis archive', 'keyword search', 'repository', 'web-based'], 'sdgs' => [4, 9], 'views' => 356, 'downloads' => 73,
                'abstract' => 'A web-based thesis archive for the college where students and faculty can search approved theses by keyword, adviser and year, and download abstracts.'],
            $base + ['public_id' => 'TUA-2021-1423', 'title' => 'Plagiarism Detection for Student Essays Using Cosine Similarity', 'program' => 'BS Computer Science', 'year' => 2021, 'research_type' => 'undergraduate_thesis', 'adviser' => 'Dr. Evelyn Sarmiento', 'authors' => ['Yuri Navarro', 'Wendy Cruz'], 'keywords' => ['plagiarism', 'cosine similarity', 'essays', 'text mining'], 'sdgs' => [4], 'views' => 301, 'downloads' => 64,
                'abstract' => 'This thesis built a tool that compares student essays with one another and with online sources. Essays are represented as term-frequency vectors and compared with cosine similarity, and pairs above a set threshold are flagged for the teacher to review.'],
            $base + ['public_id' => 'TUA-2024-1424', 'title' => 'Research Abstract Classification by Program Using TF-IDF and Naive Bayes', 'program' => 'BS Computer Science', 'year' => 2024, 'research_type' => 'undergraduate_thesis', 'adviser' => 'Prof. Dennis Galang', 'authors' => ['Oscar Lim', 'Katrina Bautista'], 'keywords' => ['tf-idf', 'naive bayes', 'text classification', 'research abstracts'], 'sdgs' => [4, 9], 'views' => 198, 'downloads' => 40,
                'abstract' => 'Research abstracts from the university library were converted into TF-IDF features and classified by program with a Naive Bayes model to speed up cataloging of theses.'],
        ];
    }

    /** @param  list<array<string, mixed>>  $records */
    private function insert(array $records): void
    {
        $units = AcademicUnit::pluck('id', 'code')->all();
        $programIds = [];
        foreach (Program::with('academicUnit')->get() as $p) {
            $programIds[$p->academicUnit->code][$p->name] = $p->id;
        }
        $advisers = User::pluck('id', 'name')->all();
        $placeholders = $this->placeholderFiles();
        $now = now();

        DB::transaction(function () use ($records, $units, $programIds, $advisers, $placeholders, $now): void {
            foreach (array_chunk($records, 400) as $chunk) {
                DB::table('research_records')->insert(array_map(function (array $r) use ($units, $programIds, $advisers, $now): array {
                    $published = Carbon::create($r['year'], 1, 1)->addDays((int) floor($this->rnd() * 361))->setTime(10, 0);

                    return [
                        'public_id' => $r['public_id'], 'title' => $r['title'], 'abstract' => $r['abstract'], 'year' => $r['year'],
                        'research_type' => $r['research_type'], 'degree_level' => $r['degree_level'],
                        'academic_unit_id' => $units[$r['college']], 'program_id' => $programIds[$r['college']][$r['program']] ?? null,
                        'adviser_user_id' => $advisers[$r['adviser']] ?? null, 'adviser_name' => $r['adviser'],
                        'status' => 'published', 'published_at' => $published, 'views' => $r['views'], 'downloads' => $r['downloads'],
                        'source' => 'legacy_import', 'imrad_not_applicable' => $r['imrad_na'], 'is_complete' => false,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }, $chunk));
            }

            $ids = DB::table('research_records')->pluck('id', 'public_id')->all();

            $allTerms = array_values(array_unique(array_merge(...array_map(fn ($r) => $r['keywords'], $records))));
            foreach (array_chunk($allTerms, 500) as $chunk) {
                DB::table('keywords')->insertOrIgnore(array_map(fn ($t) => ['term' => $t], $chunk));
            }
            $termIds = DB::table('keywords')->pluck('id', 'term')->all();

            $authors = $kw = $sdg = $files = [];
            foreach ($records as $r) {
                $id = $ids[$r['public_id']];
                foreach (array_values($r['authors']) as $pos => $name) {
                    $authors[] = ['record_id' => $id, 'name' => $name, 'position' => $pos + 1];
                }
                foreach ($r['keywords'] as $t) {
                    $kw[] = ['record_id' => $id, 'keyword_id' => $termIds[$t], 'source' => 'import'];
                }
                foreach ($r['sdgs'] as $s) {
                    $sdg[] = ['record_id' => $id, 'sdg_id' => $s, 'source' => 'import'];
                }

                $slots = array_keys(self::SLOT_FILES);
                if ($r['imrad_na']) {
                    $slots = array_values(array_diff($slots, ['imrad']));
                }
                if (! $r['complete']) {
                    // Drop 1 or 2 required files; the signed TOA is the most often missing.
                    $drop = [(string) $this->wpick(['toa' => 40, 'full_paper' => 25, 'references' => 20, 'abstract' => 15])];
                    if ($this->rnd() < 1 / 3) {
                        $drop[] = (string) $this->wpick(['toa' => 40, 'full_paper' => 25, 'references' => 20, 'abstract' => 15]);
                    }
                    $slots = array_values(array_diff($slots, $drop));
                }
                foreach ($slots as $slot) {
                    $files[] = $placeholders[$slot] + ['record_id' => $id, 'slot' => $slot, 'is_current' => true, 'created_at' => $now, 'updated_at' => $now];
                }
            }

            foreach (array_chunk($authors, 1000) as $c) {
                DB::table('record_authors')->insert($c);
            }
            foreach (array_chunk($kw, 1000) as $c) {
                DB::table('record_keyword')->insert($c);
            }
            foreach (array_chunk($sdg, 1000) as $c) {
                DB::table('record_sdg')->insert($c);
            }
            foreach (array_chunk($files, 1000) as $c) {
                DB::table('record_files')->insert($c);
            }

            // is_complete = every required slot present (IMRAD not required when marked not applicable).
            DB::statement("UPDATE research_records r SET is_complete = (
                SELECT COUNT(DISTINCT f.slot) FROM record_files f WHERE f.record_id = r.id AND f.is_current = 1
                  AND f.slot IN ('abstract','full_paper','toa','references','imrad')
            ) >= (CASE WHEN r.imrad_not_applicable THEN 4 ELSE 5 END)");
        });
    }

    /** One placeholder document per slot, stored once on the private research disk. @return array<string, array<string, mixed>> */
    private function placeholderFiles(): array
    {
        $disk = Storage::disk('research');
        $out = [];
        foreach (self::SLOT_FILES as $slot => [$file, $original]) {
            $path = '_samples/'.$file;
            $label = 'TUA Research Repository: sample '.str_replace('_', ' ', $slot).' document. Sample data only.';
            $bytes = str_ends_with($file, '.pdf') ? SampleDocuments::pdf($label) : SampleDocuments::docx($label);
            $disk->put($path, $bytes);
            $out[$slot] = [
                'path' => $path,
                'original_name' => $original,
                'mime' => str_ends_with($file, '.pdf') ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
                'uploaded_by' => null,
            ];
        }

        return $out;
    }
}
