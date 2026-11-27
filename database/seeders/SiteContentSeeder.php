<?php

namespace Database\Seeders;

use App\Domain\Site\SiteContent;
use App\Models\SiteContentVersion;
use App\Models\User;
use Illuminate\Database\Seeder;

/** [Not in manuscript] Version 1 of the landing page content, published (CLAUDE.md §8). Safe in production. */
class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        if (SiteContentVersion::exists()) {
            return;
        }

        SiteContentVersion::create([
            'version' => 1,
            'data' => SiteContent::defaults(),
            'status' => 'published',
            'summary' => 'Initial content',
            'published_by' => User::where('email', 'urdc.admin@'.config('tua.email_domain'))->value('id'),
            'published_at' => now(),
        ]);
    }
}
