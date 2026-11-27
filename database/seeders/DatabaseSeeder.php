<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database:
     * - always: reference data, roles, settings, landing page content (version 1)
     * - local and evaluation copies only: demo users, 1,419 sample records, and the sample
     *   workflow data (submissions, requests, clustering runs, notifications, search logs).
     */
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            RoleSeeder::class,
            SettingsSeeder::class,
            DemoUserSeeder::class,
            SiteContentSeeder::class,
            SampleRecordSeeder::class,
            WorkflowSampleSeeder::class,
        ]);
    }
}
