<?php

/*
|--------------------------------------------------------------------------
| TUA Research Repository settings
|--------------------------------------------------------------------------
| Defaults for values that CLAUDE.md marks as settings. Values that the URDC
| or System administrator can change at runtime are copied into the
| `settings` table by the SettingsSeeder; this file holds the defaults and
| the deploy-time switches.
*/

return [

    // Only this domain may register and sign in (CLAUDE.md §1 rule 1).
    'email_domain' => env('TUA_EMAIL_DOMAIN', 'tua.edu.ph'),

    // Separate evaluation copy with temporary evaluator accounts (§17).
    'evaluation_copy' => (bool) env('APP_EVALUATION_COPY', false),

    // Evaluator accounts expire on this date by default (the end of the evaluation period).
    'evaluation' => [
        'ends_on' => env('EVALUATION_ENDS_ON'),
        'max_days' => 120,
    ],

    // Queue connection for long jobs (backups, restores, clustering): see config/queue.php 'long'.
    'long_queue' => env('QUEUE_LONG_CONNECTION', 'long'),

    // Backups (§7 M9, §19). Restores always go to the staging database first.
    'backup' => [
        'staging_database' => env('BACKUP_STAGING_DATABASE', env('DB_DATABASE', 'tua_repository').'_staging'),
        // Folder of mysql / mysqldump; empty = on the PATH (Ubuntu). XAMPP: C:/xampp/mysql/bin
        'mysql_bin' => env('DB_DUMP_BINARY_PATH', ''),
        'daily_at' => '02:00',
    ],

    // Features marked [Not in manuscript]: keep them easy to switch off.
    'features' => [
        'landing_cms' => (bool) env('FEATURE_LANDING_CMS', true),
        'theme_picker' => (bool) env('FEATURE_THEME_PICKER', true),
        'recovery_email' => (bool) env('RECOVERY_EMAIL_ENABLED', true),
        'recovery_sms' => (bool) env('RECOVERY_SMS_ENABLED', false),
    ],

    'auth' => [
        'activation_link_hours' => 48,
        'password_min_length' => 10,
        'login_max_attempts' => 5,
        'login_decay_seconds' => 60,
        'recovery_code_minutes' => 10,
        'recovery_code_max_attempts' => 5,
    ],

    'repository' => [
        'abstract_word_limit' => 250,
        'keywords_min' => 3,
        'keywords_max' => 5,
        'max_file_mb' => 20,
        // The five typed file slots (§7 M2).
        'file_slots' => [
            'abstract' => ['label' => 'Abstract (URDC format)', 'mimes' => ['doc', 'docx'], 'required' => true],
            'full_paper' => ['label' => 'Full paper', 'mimes' => ['pdf'], 'required' => true],
            'imrad' => ['label' => 'IMRAD version', 'mimes' => ['pdf'], 'required' => true, 'can_be_not_applicable' => true],
            'toa' => ['label' => 'Signed Transfer of Ownership Agreement (R-12-01)', 'mimes' => ['pdf'], 'required' => true],
            'references' => ['label' => 'List of references', 'mimes' => ['doc', 'docx'], 'required' => true],
        ],
        'storage_disk' => 'research',
    ],

    // Similarity flags (§7 M5). The review threshold must stay below the duplicate threshold.
    'screening' => [
        'duplicate_threshold' => 0.80,
        'review_threshold' => 0.60,
        'top_k' => 5,
    ],

    'clustering' => [
        'k_min' => 2,
        'k_max' => 15,
        'refit_after_records' => 50,
        'schedule' => 'semester',
    ],

    'analytics_service' => [
        'url' => env('ANALYTICS_URL', 'http://127.0.0.1:8001'),
        'hmac_secret' => env('ANALYTICS_HMAC_SECRET'),
        'timeout' => (int) env('ANALYTICS_TIMEOUT', 30),
        'max_clock_skew_seconds' => 60,
        'retries' => 3,
    ],

    'llm' => [
        'enabled' => (bool) env('LLM_ENABLED', false),
        'provider' => env('LLM_PROVIDER'),
        'api_key' => env('LLM_API_KEY'),
        'model' => env('LLM_MODEL'),
        'timeout' => (int) env('LLM_TIMEOUT', 8),
        'rate_per_minute' => (int) env('LLM_RATE_PER_MINUTE', 60),
        // Read-only list shown in System settings: the only fields ever sent (§10).
        'fields_sent' => ['title', 'abstract', 'keywords', 'cluster_terms'],
        // Date the Data Privacy Officer approved the LLM service (§10), shown in Services and security.
        'dpo_approved_on' => env('LLM_DPO_APPROVED_ON'),
    ],

    'sms' => [
        'provider' => env('SMS_PROVIDER'),
        'api_key' => env('SMS_API_KEY'),
    ],

    // Theme swatches (§9). Navy is the default.
    'theme' => [
        // Default = the landing page's deep green, so signed-in pages match the public site.
        'default' => '#1b4a42',
        'swatches' => [
            'TUA Green' => '#1b4a42', 'Navy' => '#1e3a6b', 'Maroon' => '#7a1f2b', 'Teal' => '#0f5e66',
            'Violet' => '#4b3a8c', 'Rust' => '#8a3b12', 'Slate' => '#334155', 'Magenta' => '#9c2a6b',
        ],
    ],
];
