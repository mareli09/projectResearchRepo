<?php

namespace App\Http\Controllers\Design;

use App\Domain\Site\DesignData;
use App\Http\Controllers\Controller;
use App\Models\MailLog;
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

    public function backups(): View
    {
        return view('admin.backups', ['backups' => DesignData::backups()]);
    }

    public function evaluators(): View
    {
        return view('admin.evaluators', ['evaluators' => DesignData::evaluators(), 'isEvalCopy' => (bool) config('tua.evaluation_copy')]);
    }
}
