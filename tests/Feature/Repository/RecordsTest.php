<?php

use App\Domain\Audit\Activity;
use App\Models\AcademicUnit;
use App\Models\Program;
use App\Models\ResearchRecord;
use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    seedBasics();
    $this->urdc = userWithRole(User::ROLE_URDC);
});

function editPayload(ResearchRecord $r, array $overrides = []): array
{
    return array_merge([
        'title' => $r->title,
        'abstract' => $r->abstract,
        'authors' => "Francine Garcia\nEnzo Navarro",
        'adviser_name' => $r->adviser_name,
        'academic_unit_id' => $r->academic_unit_id,
        'program_id' => $r->program_id,
        'research_type' => $r->research_type,
        'degree_level' => $r->degree_level,
        'year' => $r->year,
        'keywords' => 'flood risk, random forest, prediction',
        'sdgs' => [11, 13],
    ], $overrides);
}

it('is only for the URDC', function (string $role) {
    $r = makeRecord();
    $this->actingAs(userWithRole($role))->get('/urdc/records')->assertForbidden();
    $this->actingAs(userWithRole($role))->get("/urdc/records/{$r->id}")->assertForbidden();
    $this->actingAs(userWithRole($role))->put("/urdc/records/{$r->id}", editPayload($r))->assertForbidden();
})->with([User::ROLE_STUDENT, User::ROLE_ADVISER, User::ROLE_COORDINATOR, User::ROLE_SYSADMIN]);

it('lists, searches and filters by college', function () {
    makeRecord(['title' => 'Mobile Attendance Monitoring With QR Codes']);
    makeRecord(['title' => 'Maternal Health Practices of First-Time Mothers', 'academic_unit_id' => AcademicUnit::where('code', 'SLCN')->value('id'), 'program_id' => null]);

    $this->actingAs($this->urdc)->get('/urdc/records')->assertOk()->assertSee('Mobile Attendance')->assertSee('Maternal Health');
    $this->get('/urdc/records?q=Attendance')->assertSee('Mobile Attendance')->assertDontSee('Maternal Health');
    $this->get('/urdc/records?college=SLCN')->assertSee('Maternal Health')->assertDontSee('Mobile Attendance');
});

it('filters by missing data: no keywords, no SDG, incomplete files', function () {
    $noSdg = makeRecord(['title' => 'Record Without Any SDG', 'sdgs' => []]);
    $noKw = makeRecord(['title' => 'Record With Few Keywords']);
    $noKw->keywords()->detach();

    $this->actingAs($this->urdc);
    $this->get('/urdc/records?missing=sdg')->assertSee('Record Without Any SDG')->assertDontSee('Record With Few Keywords');
    $this->get('/urdc/records?missing=keywords')->assertSee('Record With Few Keywords')->assertDontSee('Record Without Any SDG');
    $this->get('/urdc/records?missing=files')->assertSee('Record Without Any SDG')->assertSee('Record With Few Keywords');
});

it('edits metadata and logs old and new values', function () {
    $r = makeRecord(['title' => 'Old Title For The Record']);

    $this->actingAs($this->urdc)->put("/urdc/records/{$r->id}", editPayload($r, [
        'title' => 'New Title For The Record',
        'keywords' => 'flood risk, random forest, prediction, barangay',
    ]))->assertSessionHasNoErrors();

    $r->refresh();
    expect($r->title)->toBe('New Title For The Record');
    expect($r->keywords()->count())->toBe(4);

    $entry = Activity::where('event', 'metadata_edit')->where('subject_id', $r->id)->latest('id')->firstOrFail();
    expect($entry->properties['old']['title'])->toBe('Old Title For The Record');
    expect($entry->properties['new']['title'])->toBe('New Title For The Record');
    expect($entry->properties['new']['keywords'])->toContain('barangay');
    expect($entry->properties['old'])->not->toHaveKey('abstract'); // only changed fields
    expect($entry->causer_id)->toBe($this->urdc->id);
});

it('refuses fewer than 3 or more than 5 keywords', function (string $keywords) {
    $r = makeRecord();
    $this->actingAs($this->urdc)->put("/urdc/records/{$r->id}", editPayload($r, ['keywords' => $keywords]))->assertSessionHasErrors('keywords');
})->with([
    'two' => 'flood, rain',
    'six' => 'a1, b2, c3, d4, e5, f6',
    'duplicates collapse to two' => 'Flood, flood , FLOOD, rain',
]);

it('enforces the abstract word limit from Settings', function () {
    $r = makeRecord();
    $this->actingAs($this->urdc);

    $this->put("/urdc/records/{$r->id}", editPayload($r, ['abstract' => str_repeat('word ', 251)]))->assertSessionHasErrors('abstract');
    $this->put("/urdc/records/{$r->id}", editPayload($r, ['abstract' => str_repeat('word ', 250)]))->assertSessionHasNoErrors();

    Setting::updateOrCreate(['key' => 'repository.abstract_word_limit'], ['value' => 100]);
    $this->put("/urdc/records/{$r->id}", editPayload($r, ['abstract' => str_repeat('word ', 101)]))
        ->assertSessionHasErrors(['abstract' => 'The abstract has 101 words. The limit is 100.']);
});

it('refuses SDG numbers outside 1–17 and a program from another college', function () {
    $r = makeRecord();
    $this->actingAs($this->urdc);
    $this->put("/urdc/records/{$r->id}", editPayload($r, ['sdgs' => [18]]))->assertSessionHasErrors('sdgs.0');
    $pharmacy = Program::where('name', 'BS Pharmacy')->value('id');
    $this->put("/urdc/records/{$r->id}", editPayload($r, ['program_id' => $pharmacy]))->assertSessionHasErrors('program_id');
});

it('withdraws a record with a reason (soft delete)', function () {
    $r = makeRecord();
    $this->actingAs($this->urdc);

    $this->post("/urdc/records/{$r->id}/withdraw", [])->assertSessionHasErrors('reason');
    $this->post("/urdc/records/{$r->id}/withdraw", ['reason' => 'Duplicate of an older record'])->assertRedirect('/urdc/records');

    expect(ResearchRecord::find($r->id))->toBeNull();
    $trashed = ResearchRecord::withTrashed()->find($r->id);
    expect($trashed->status)->toBe('withdrawn');
    $this->get('/urdc/records?status=withdrawn')->assertSee($r->title);
    $this->get("/urdc/records/{$r->id}")->assertOk()->assertSee('Withdrawn')->assertDontSee('Save metadata');
});

it('gives new records IDs like TUA-2025-0001', function () {
    expect(makeRecord(['year' => 2025])->public_id)->toBe('TUA-2025-0001');
    expect(makeRecord(['year' => 2019])->public_id)->toBe('TUA-2019-0002');
});
