<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Backups;
use App\Domain\Admin\SystemHealth;
use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Http\Controllers\Controller;
use App\Services\LlmClient\LlmClient;
use App\Services\LlmClient\NullLlmClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/** "System health" and "Services and security" (CLAUDE.md §7 M9), System administrator. */
class HealthController extends Controller
{
    public function health(): View
    {
        $analytics = SystemHealth::analytics();

        return view('admin.health', [
            'queue' => DB::table('jobs')->count(),
            'failed' => DB::table('failed_jobs')->count(),
            'mail' => SystemHealth::mail(),
            'disk' => SystemHealth::disk(),
            'filesGb' => SystemHealth::filesGb(),
            'dbGb' => SystemHealth::databaseGb(),
            'memory' => SystemHealth::memory(),
            'analytics' => $analytics,
            'llm' => SystemHealth::llmConfigured(),
            'jobs' => SystemHealth::jobs(),
            'backup' => Backups::lastRun(),
        ]);
    }

    public function services(): View
    {
        return view('admin.services', [
            'calls' => SystemHealth::recentCalls(),
            'llmEnabled' => SystemHealth::llmConfigured(),
            'fields' => config('tua.llm.fields_sent'),
            'mail' => SystemHealth::mail(),
            'dpo' => config('tua.llm.dpo_approved_on'),
        ]);
    }

    public function retryFailed(Request $request): RedirectResponse
    {
        $n = DB::table('failed_jobs')->count();
        Artisan::call('queue:retry', ['id' => ['all']]);
        Audit::record(AuditEvent::SERVICE, "Retried {$n} failed jobs", null, ['service' => 'queue', 'purpose' => 'retry', 'result' => 'ok', 'jobs' => $n], $request->user());

        return back()->with('status', $n ? "{$n} failed jobs were put back on the queue." : 'There are no failed jobs.');
    }

    public function test(Request $request, string $service, LlmClient $llm): RedirectResponse
    {
        $by = $request->user();

        if ($service === 'analytics') {
            $r = SystemHealth::analytics();
            Audit::record(AuditEvent::SERVICE, 'Analytics service: connection test', null, ['service' => 'analytics', 'purpose' => 'test', 'fields_sent' => [], 'result' => $r['ok'] ? 'ok' : 'unavailable', 'ms' => $r['ms']], $by);

            return back()->with('status', $r['ok'] ? "Analytics service is running: {$r['detail']}." : "Analytics service is not reachable: {$r['detail']}");
        }

        if ($service === 'llm') {
            if ($llm instanceof NullLlmClient) {
                return back()->with('status', 'The LLM is turned off (LLM_ENABLED, LLM_API_KEY or the System settings switch), so the top-terms fallback is used.');
            }
            // A fixed test text: no repository data is sent.
            $r = $llm->suggestThemeLabel(['library', 'catalog', 'search', 'records'], ['A test of the repository catalog']);

            return back()->with('status', $r ? "The LLM replied with a valid label: \"{$r['label']}\"." : 'The LLM did not return a valid reply. Suggestions fall back to top terms. See the activity log.');
        }

        if ($service === 'mail') {
            try {
                Mail::raw("This is a test email from the TUA Research Repository.\n\nIf you can read this, the mail settings work.", function ($m) use ($by) {
                    $m->to($by->email)->subject('Test email from the TUA Research Repository');
                });
                $result = 'ok';
            } catch (Throwable $e) {
                $result = 'failed: '.mb_substr($e->getMessage(), 0, 120);
            }
            Audit::record(AuditEvent::SERVICE, 'Mail: test email', null, ['service' => 'mail', 'purpose' => 'test', 'fields_sent' => [], 'result' => $result === 'ok' ? 'ok' : 'failed'], $by);

            return back()->with('status', $result === 'ok' ? "A test email was sent to {$by->email}." : "The test email could not be sent ({$result}).");
        }

        abort(404);
    }
}
