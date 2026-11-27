<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\DashboardTasks;
use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\DashboardTaskRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Dashboard task mode (CLAUDE.md §7 M7, SOP 3.3): URDC settings and results; evaluators start and finish tasks. */
class DashboardTaskController extends Controller
{
    public function index(): View
    {
        return view('urdc.tasks', [
            'config' => DashboardTasks::config(),
            'results' => DashboardTasks::results(),
            'summary' => DashboardTasks::summary(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'on' => ['boolean'],
            'audience' => ['required', 'in:evaluators,dashboard_users'],
            'tasks' => ['required', 'array', 'size:'.DashboardTasks::COUNT],
            'tasks.*' => ['required', 'string', 'max:300'],
        ], ['tasks.*.required' => 'Write all five tasks.']);

        DashboardTasks::save([
            'on' => (bool) ($data['on'] ?? false),
            'audience' => $data['audience'],
            'tasks' => array_values(array_map(fn ($t) => trim(strip_tags((string) $t)), $data['tasks'])),
        ], $request->user());

        return back()->with('status', 'Task mode saved.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $n = DashboardTaskRun::count();
        DashboardTaskRun::query()->delete();
        Audit::record(AuditEvent::SETTING, "Dashboard task results cleared ({$n} rows)", null, [], $request->user());

        return back()->with('status', 'The task results were cleared.');
    }

    public function export(): StreamedResponse
    {
        $results = DashboardTasks::results();
        $tasks = DashboardTasks::config()['tasks'];

        return response()->streamDownload(function () use ($results, $tasks): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Evaluator', 'Email', 'Task', 'Task text', 'Status', 'Seconds', 'Answer']);
            foreach ($results as $r) {
                foreach ($r['tasks'] as $no => $t) {
                    fputcsv($out, [$r['user'], $r['email'], $no, $tasks[$no - 1] ?? '', $t['status'], $t['seconds'] ?? '', self::safe((string) $t['answer'])]);
                }
            }
            fclose($out);
        }, 'dashboard-tasks-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function start(Request $request, int $no): RedirectResponse
    {
        abort_unless(DashboardTasks::appliesTo($request->user()), 403);
        DashboardTasks::start($request->user(), $no);

        return back();
    }

    public function finish(Request $request, int $no): RedirectResponse
    {
        abort_unless(DashboardTasks::appliesTo($request->user()), 403);
        $data = $request->validate(['status' => ['required', 'in:done,skipped'], 'answer' => ['nullable', 'string', 'max:500']]);
        DashboardTasks::finish($request->user(), $no, $data['status'], $data['answer'] ?? null);

        return back()->with('status', $no < DashboardTasks::COUNT ? 'Saved. Here is the next task.' : 'All five tasks are finished. Thank you.');
    }

    private static function safe(string $v): string
    {
        return $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'".$v : $v;
    }
}
