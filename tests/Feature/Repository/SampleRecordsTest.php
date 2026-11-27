<?php

use App\Domain\Repository\FileSlots;
use App\Models\ResearchRecord;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\SampleRecordSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('seeds 1,419 realistic sample records that meet the M2 rules', function () {
    Storage::fake('research');
    seedBasics();
    $this->seed([DemoUserSeeder::class, SampleRecordSeeder::class]);

    expect(ResearchRecord::count())->toBe(1419);
    expect(ResearchRecord::distinct()->count('title'))->toBe(1419);
    expect(ResearchRecord::published()->count())->toBe(1419);

    $kw = DB::table('record_keyword')->selectRaw('record_id, COUNT(*) c')->groupBy('record_id')->pluck('c');
    expect($kw)->toHaveCount(1419);
    expect($kw->min())->toBeGreaterThanOrEqual(3);
    expect($kw->max())->toBeLessThanOrEqual(5);
    expect(ResearchRecord::query()->missingSdg()->count())->toBe(0);

    $complete = ResearchRecord::where('is_complete', true)->count() / 1419;
    expect($complete)->toBeGreaterThan(0.75)->toBeLessThan(0.88);

    // is_complete agrees with the file slot rules.
    foreach (ResearchRecord::inRandomOrder()->limit(25)->get() as $r) {
        expect($r->is_complete)->toBe(FileSlots::missingSlots($r) === []);
    }

    // The planted near-duplicates for the screening demo.
    expect(ResearchRecord::where('public_id', 'TUA-2023-1420')->value('title'))->toContain('TF-IDF and Cosine Similarity');

    // Placeholder files are real documents that pass the slot checks.
    foreach (['full_paper' => 'full_paper.pdf', 'abstract' => 'abstract.docx'] as $slot => $file) {
        expect(FileSlots::check($slot, Storage::disk('research')->path('_samples/'.$file), $file))->toBeNull();
    }
});
