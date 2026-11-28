<?php

use App\Domain\Search\RecordSearch;
use Illuminate\Support\Facades\Cache;

/*
 * MySQL FULLTEXT search (CLAUDE.md §7 M4) on committed rows. InnoDB updates its full-text index
 * only at commit, so these tests use DatabaseTruncation instead of a rolled-back transaction
 * (tests/Pest.php), and empty the tables again afterwards so the Feature tests start clean.
 */
beforeEach(function () {
    seedBasics();
    Cache::flush();
    $publish = fn ($r) => $r->forceFill(['status' => 'published', 'published_at' => now()])->save();
    $publish(makeRecord([
        'title' => 'Barangay Health Records Dashboard',
        'abstract' => 'A dashboard of health records for barangay health workers, with learning modules on records keeping.',
        'keywords' => 'health records, dashboard, barangay',
    ]));
    $publish(makeRecord([
        'title' => 'Learning Analytics for Machine Shop Courses',
        'abstract' => 'Machine learning models predict which students in machine shop courses need support. Learning analytics and machine learning results are shown to instructors.',
        'keywords' => 'machine learning, learning analytics, prediction',
    ]));
    $publish(makeRecord([
        'title' => 'Inventory System for a Canteen',
        'abstract' => 'An inventory and ordering system for the university canteen.',
        'keywords' => 'inventory, ordering, canteen',
    ]));
});

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

it('matches all words in any order, and the start of a word', function () {
    $hits = RecordSearch::run(['q' => 'learning machine'])->pluck('title')->all();
    expect($hits)->toBe(['Learning Analytics for Machine Shop Courses']);

    $prefix = RecordSearch::run(['q' => 'invent canteen'])->pluck('title')->all();
    expect($prefix)->toBe(['Inventory System for a Canteen']);
});

it('ranks by the FULLTEXT score when no title holds the whole query', function () {
    $hits = RecordSearch::run(['q' => 'learning records', 'sort' => 'relevance']);
    // Only the health-records study holds both words; the substring part adds nothing here.
    expect($hits->pluck('title')->all())->toBe(['Barangay Health Records Dashboard']);

    // Neither title holds "panel solar", so the order comes from the FULLTEXT score alone.
    $publish = fn ($r) => $r->forceFill(['status' => 'published', 'published_at' => now()])->save();
    $publish(makeRecord(['title' => 'Energy Monitoring for Campus Buildings', 'abstract' => 'Tracks solar output and the panel cleaning schedule of campus buildings.', 'keywords' => 'energy, monitoring, buildings']));
    $publish(makeRecord(['title' => 'Rooftop Power for Public Schools', 'abstract' => 'Solar panel arrays on school rooftops. Solar panel tilt, solar panel cleaning and solar panel output are compared across schools.', 'keywords' => 'solar panel, rooftop, schools']));

    $ranked = RecordSearch::run(['q' => 'panel solar', 'sort' => 'relevance'])->pluck('title')->all();
    expect($ranked)->toBe(['Rooftop Power for Public Schools', 'Energy Monitoring for Campus Buildings']);
});
