<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\SystemSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "System settings" (CLAUDE.md §7 M9): one form per group, every change logged. */
class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', [
            'values' => collect(array_keys(SystemSettings::TITLES))->mapWithKeys(fn ($g) => [$g => SystemSettings::current($g)])->all(),
            'fields' => config('tua.llm.fields_sent'),
            'recoveryBuilt' => ['recovery_email' => (bool) config('tua.features.recovery_email'), 'sms' => (bool) config('tua.features.recovery_sms')],
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        abort_unless(array_key_exists($group, SystemSettings::TITLES), 404);
        $data = $request->validateWithBag($group, SystemSettings::rules()[$group], SystemSettings::messages());

        $changes = SystemSettings::save($group, $data, $request->user());

        return redirect()->route('admin.settings')->withFragment($group)->with('status', $changes === []
            ? SystemSettings::TITLES[$group].': nothing changed.'
            : SystemSettings::TITLES[$group].' saved. The change is in the activity log.');
    }
}
