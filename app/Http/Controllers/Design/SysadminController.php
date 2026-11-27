<?php

namespace App\Http\Controllers\Design;

use App\Domain\Site\DesignData;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** System administrator screens (CLAUDE.md §7 M9, §17). */
class SysadminController extends Controller
{
    public function backups(): View
    {
        return view('admin.backups', ['backups' => DesignData::backups()]);
    }

    public function evaluators(): View
    {
        return view('admin.evaluators', ['evaluators' => DesignData::evaluators(), 'isEvalCopy' => (bool) config('tua.evaluation_copy')]);
    }
}
