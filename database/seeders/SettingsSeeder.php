<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/** Copies defaults from config/tua.php into the settings table. Existing values are kept. */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'screening.thresholds' => [
                'duplicate' => config('tua.screening.duplicate_threshold'),
                'review' => config('tua.screening.review_threshold'),
            ],
            'clustering.schedule' => ['every' => config('tua.clustering.schedule'), 'after_new_records' => config('tua.clustering.refit_after_records')],
            'files.max_mb' => config('tua.repository.max_file_mb'),
            'repository.abstract_word_limit' => config('tua.repository.abstract_word_limit'),
            'session.idle_minutes' => (int) config('session.lifetime'),
            'auth.login_max_attempts' => config('tua.auth.login_max_attempts'),
            'llm.enabled' => config('tua.llm.enabled'),
            'llm.timeout' => config('tua.llm.timeout'),
            'llm.rate_per_minute' => config('tua.llm.rate_per_minute'),
            'recovery.channels' => [
                'tua_email' => true,
                'recovery_email' => config('tua.features.recovery_email'),
                'sms' => config('tua.features.recovery_sms'),
            ],
            'site.theme' => ['default' => config('tua.theme.default'), 'users_can_pick' => true],
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
