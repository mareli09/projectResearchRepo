<?php

use App\Domain\Search\RecordSearch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/*
 * Search and catalog (CLAUDE.md §7 M4): the program facet and the parts of text search that work
 * inside a test transaction. InnoDB's FULLTEXT index only sees committed rows, so word-order and
 * relevance ranking are tested in tests/Integration/FullTextSearchTest.php.
 */
beforeEach(function () {
    seedBasics();
    Cache::flush();
    $this->student = userWithRole(User::ROLE_STUDENT);
    $this->bscs = (int) Program::where('name', 'BS Computer Science')->value('id');
    $publish = fn ($r) => $r->forceFill(['status' => 'published', 'published_at' => now()])->save();
    $publish(makeRecord(['title' => 'Flood Risk Prediction Using Random Forest']));
    $publish(makeRecord(['title' => 'AI Tutor for Programming Courses', 'program_id' => $this->bscs, 'keywords' => 'tutoring, programming, feedback']));
    $publish(makeRecord(['title' => 'Water Quality of the Pasig River 100% Sampled', 'program_id' => $this->bscs, 'keywords' => 'water quality, sampling, river']));
});

it('filters by program and lists only the chosen college’s programs', function () {
    $this->actingAs($this->student)->get('/search?program='.$this->bscs)->assertOk()
        ->assertSee('AI Tutor for Programming Courses')->assertDontSee('Flood Risk Prediction Using Random Forest');

    $ceis = RecordSearch::facets('CEIS')['program'];
    expect($ceis)->toContain('BS Computer Science')->not->toContain('BS Medical Technology')
        ->and(RecordSearch::facets()['program'])->toContain('CEIS · BS Computer Science');
});

it('drops a program that does not belong to the chosen college', function () {
    $medtech = (int) Program::where('name', 'BS Medical Technology')->value('id');
    $this->actingAs($this->student)->get('/search?college=CEIS&program='.$medtech)->assertOk()
        ->assertSee('Flood Risk Prediction Using Random Forest')->assertSee('AI Tutor for Programming Courses');
});

it('finds short terms and exact phrases, and treats % and _ as plain characters', function () {
    $this->actingAs($this->student)->get('/search?q=AI')->assertOk()->assertSee('AI Tutor for Programming Courses');
    $this->actingAs($this->student)->get('/search?q=water+quality')->assertSee('Water Quality of the Pasig River');
    $this->actingAs($this->student)->get('/search?q=%25')->assertSee('Water Quality of the Pasig River')
        ->assertDontSee('Flood Risk Prediction Using Random Forest');
    $this->get('/catalog?q=tutoring')->assertOk()->assertSee('AI Tutor for Programming Courses');
});

it('builds a safe FULLTEXT boolean query', function () {
    expect(RecordSearch::booleanQuery('Machine learning for the barangay'))->toBe('+machine* +learning* +barangay*')
        ->and(RecordSearch::booleanQuery('AI in IT'))->toBe('')
        ->and(RecordSearch::booleanQuery('flood +risk -"x" (y) ~z*'))->toBe('+flood* +risk*');
});
