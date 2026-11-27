<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The default theme color is now the landing page green (#1b4a42) instead of navy, so signed-in
 * pages match the public site. Landing content that still has the old navy default is updated;
 * a color the URDC chose on purpose is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->swap('#1e3a6b', '#1b4a42');
    }

    public function down(): void
    {
        $this->swap('#1b4a42', '#1e3a6b');
    }

    private function swap(string $from, string $to): void
    {
        foreach (DB::table('site_contents')->get(['id', 'data']) as $row) {
            $data = json_decode((string) $row->data, true);
            if (is_array($data) && strtolower((string) ($data['theme']['color'] ?? '')) === $from) {
                $data['theme']['color'] = $to;
                DB::table('site_contents')->where('id', $row->id)->update(['data' => json_encode($data)]);
            }
        }
    }
};
