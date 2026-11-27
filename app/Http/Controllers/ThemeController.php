<?php

namespace App\Http\Controllers;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** [Not in manuscript] Saves the theme color and appearance to the user (CLAUDE.md §9). */
class ThemeController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        abort_unless(config('tua.features.theme_picker'), 404);
        $data = $request->validate([
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'mode' => ['nullable', 'in:system,light,dark'],
        ]);

        $user = $request->user();
        $old = ['color' => $user->theme_color, 'mode' => $user->theme_mode];
        $new = ['color' => isset($data['color']) ? strtolower($data['color']) : null, 'mode' => $data['mode'] ?? 'system'];

        if ($old != $new) {
            $user->forceFill(['theme_color' => $new['color'], 'theme_mode' => $new['mode']])->save();
            Audit::record(AuditEvent::THEME, 'Theme changed', $user, [], $user, old: $old, new: $new);
        }

        return response()->json(['saved' => true] + $new);
    }
}
