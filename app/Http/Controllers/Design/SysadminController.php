<?php

namespace App\Http\Controllers\Design;

use App\Domain\Site\DesignData;
use App\Http\Controllers\Controller;
use App\Models\MailLog;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** System administrator screens (CLAUDE.md §7 M9, §17). */
class SysadminController extends Controller
{
    public function health(): View
    {
        // Real where it is cheap to read now; the rest is sample until Sprint 9.
        $root = storage_path();
        $total = @disk_total_space($root) ?: 0;
        $free = @disk_free_space($root) ?: 0;

        return view('admin.health', [
            'queue' => DB::table('jobs')->count(),
            'failed' => DB::table('failed_jobs')->count(),
            'mailFailed' => MailLog::where('status', 'failed')->count(),
            'diskUsedGb' => $total ? round(($total - $free) / 1073741824, 1) : null,
            'diskTotalGb' => $total ? round($total / 1073741824, 1) : null,
            'jobs' => DesignData::jobs(),
        ]);
    }

    public function services(): View
    {
        return view('admin.services', [
            'calls' => DesignData::serviceCalls(),
            'llmEnabled' => (bool) config('tua.llm.enabled'),
            'fields' => config('tua.llm.fields_sent'),
        ]);
    }

    public function settings(): View
    {
        $get = fn (string $k, mixed $d) => Setting::valueOf($k, $d);

        return view('admin.settings', [
            'thresholds' => $get('screening.thresholds', ['duplicate' => .8, 'review' => .6]),
            'schedule' => $get('clustering.schedule', ['every' => 'semester', 'after_new_records' => 50]),
            'maxMb' => $get('files.max_mb', 20),
            'wordLimit' => $get('repository.abstract_word_limit', 250),
            'session' => $get('session.idle_minutes', 30),
            'attempts' => $get('auth.login_max_attempts', 5),
            'llm' => ['enabled' => $get('llm.enabled', false), 'timeout' => $get('llm.timeout', 8), 'rate' => $get('llm.rate_per_minute', 60)],
            'recovery' => $get('recovery.channels', ['tua_email' => true, 'recovery_email' => true, 'sms' => false]),
            'fields' => config('tua.llm.fields_sent'),
        ]);
    }

    public function backups(): View
    {
        return view('admin.backups', ['backups' => DesignData::backups()]);
    }

    public function evaluators(): View
    {
        return view('admin.evaluators', ['evaluators' => DesignData::evaluators(), 'isEvalCopy' => (bool) config('tua.evaluation_copy')]);
    }
}
