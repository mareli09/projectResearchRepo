<?php

use App\Domain\Audit\Activity;
use App\Domain\Search\UsageLog;
use App\Http\Controllers\RecordFileController;
use App\Models\AccessRequest;
use App\Models\RecordFile;
use App\Models\SearchLog;
use App\Models\User;
use App\Notifications\WorkflowNotice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
| M4 full-paper requests and usage logging (CLAUDE.md §4, §7 M4, §13).
*/
beforeEach(function () {
    Storage::fake('research');
    Cache::put('analytics.down', true, 60); // related studies from keywords, no service calls
    seedBasics();
    $this->student = userWithRole(User::ROLE_STUDENT, ['academic_unit_id' => ceisId(), 'program_id' => bsitId()]);
    $this->urdc = userWithRole(User::ROLE_URDC);
    $this->record = makeRecord(['title' => 'Telemedicine Readiness of Rural Clinics']);
    $this->record->forceFill(['status' => 'published', 'published_at' => now()])->save();
    $this->paper = RecordFile::create(['record_id' => $this->record->id, 'slot' => 'full_paper', 'path' => 'records/x/paper.pdf',
        'original_name' => 'paper.pdf', 'mime' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat('a', 64), 'uploaded_by' => $this->urdc->id, 'is_current' => true]);
    $this->toa = RecordFile::create(['record_id' => $this->record->id, 'slot' => 'toa', 'path' => 'records/x/toa.pdf',
        'original_name' => 'toa.pdf', 'mime' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat('b', 64), 'uploaded_by' => $this->urdc->id, 'is_current' => true]);
    Storage::disk('research')->put('records/x/paper.pdf', '%PDF-1.4 paper');
});

function askForPaper(string $purpose = 'Related literature for my capstone on telehealth.')
{
    return test()->actingAs(test()->student)->post('/studies/'.test()->record->public_id.'/request', ['purpose' => $purpose]);
}

it('lets a student request a full paper, and notifies the URDC', function () {
    Notification::fake();

    askForPaper()->assertSessionHasNoErrors();

    $req = AccessRequest::sole();
    expect($req->status)->toBe('pending')->and($req->public_id)->toBe('AR-001')->and($req->user_id)->toBe($this->student->id);
    Notification::assertSentTo($this->urdc, WorkflowNotice::class);
    expect(Activity::where('event', 'request')->count())->toBe(1);

    // No second request while one is pending; a purpose is required.
    askForPaper()->assertSessionHasErrors('purpose');
    askForPaper('')->assertSessionHasErrors('purpose');
    expect(AccessRequest::count())->toBe(1);

    $this->actingAs($this->student)->get('/studies/'.$this->record->public_id)->assertSee('is waiting for the URDC');
});

it('only students can request full papers', function () {
    $this->actingAs(userWithRole(User::ROLE_ADVISER))->post('/studies/'.$this->record->public_id.'/request', ['purpose' => str_repeat('x', 20)])->assertForbidden();
});

it('gives the student access until the expiry date after approval', function () {
    Notification::fake();
    askForPaper();
    $req = AccessRequest::sole();
    expect($this->student->can('download', $this->paper))->toBeFalse();

    $this->actingAs($this->urdc)->post("/urdc/requests/{$req->id}", ['decision' => 'approve', 'expires_at' => now()->addDays(14)->toDateString()])
        ->assertSessionHasNoErrors();

    expect($req->refresh()->status)->toBe('approved')->and($req->decided_by)->toBe($this->urdc->id);
    Notification::assertSentTo($this->student, WorkflowNotice::class, fn ($n) => str_contains($n->toArray($this->student)['title'] ?? json_encode($n->toArray($this->student)), 'approved'));

    $student = $this->student->fresh();
    expect($student->can('download', $this->paper))->toBeTrue()
        ->and($student->can('download', $this->toa))->toBeFalse(); // the Transfer of Ownership stays with the URDC
    $this->actingAs($student)->get(RecordFileController::signedUrl($this->paper))->assertOk();

    // After the expiry date access ends.
    $this->travelTo(now()->addDays(15));
    expect($student->fresh()->can('download', $this->paper))->toBeFalse();
});

it('requires a reason to decline and shows it to the student', function () {
    Notification::fake();
    askForPaper();
    $req = AccessRequest::sole();

    $this->actingAs($this->urdc)->post("/urdc/requests/{$req->id}", ['decision' => 'decline'])->assertSessionHasErrors('reason');
    $this->actingAs($this->urdc)->post("/urdc/requests/{$req->id}", ['decision' => 'decline', 'reason' => 'Ask your adviser for the copy kept by the department.']);

    expect($req->refresh()->status)->toBe('declined');
    $this->actingAs($this->student)->get('/studies/'.$this->record->public_id)->assertSee('Ask your adviser for the copy kept by the department.')->assertSee('Send request');

    // Already decided: cannot be decided again. A new request can be made.
    $this->actingAs($this->urdc)->post("/urdc/requests/{$req->id}", ['decision' => 'approve', 'expires_at' => now()->addDays(3)->toDateString()])->assertSessionHasErrors('decision');
    askForPaper()->assertSessionHasNoErrors();
    expect(AccessRequest::count())->toBe(2);
});

it('validates the expiry date and limits deciding to the URDC', function () {
    askForPaper();
    $req = AccessRequest::sole();
    $this->actingAs($this->urdc)->post("/urdc/requests/{$req->id}", ['decision' => 'approve', 'expires_at' => now()->subDay()->toDateString()])->assertSessionHasErrors('expires_at');
    $this->actingAs($this->student)->post("/urdc/requests/{$req->id}", ['decision' => 'approve', 'expires_at' => now()->addDay()->toDateString()])->assertForbidden();
    $this->actingAs($this->urdc)->get('/urdc/requests')->assertOk()->assertSee($req->public_id)->assertSee('Approve');
});

it('logs every search, including searches with no results', function () {
    $this->get('/catalog?q=blockchain+voting')->assertOk(); // guest
    $this->actingAs($this->student)->get('/search?q=telemedicine')->assertOk();
    $this->actingAs($this->student)->get('/search?q=blockchain+voting')->assertOk();
    $this->actingAs($this->student)->get('/search')->assertOk(); // just opening the page is not a search

    expect(SearchLog::count())->toBe(3)
        ->and(SearchLog::where('query', 'telemedicine')->value('results_count'))->toBe(1)
        ->and(SearchLog::where('query', 'blockchain voting')->whereNull('user_id')->exists())->toBeTrue();
    // "No or few results" means 3 or fewer; the most searched gap comes first.
    expect(UsageLog::gaps())->toBe([['blockchain voting', 2, 0, now()->format('M j, Y')], ['telemedicine', 1, 1, now()->format('M j, Y')]]);
    expect(Activity::where('event', 'search')->count())->toBe(2);
});

it('counts and logs every study view', function () {
    $this->get('/catalog/'.$this->record->public_id)->assertOk(); // guest
    $this->actingAs($this->student)->get('/studies/'.$this->record->public_id)->assertOk();

    expect($this->record->refresh()->views)->toBe(2)->and(Activity::where('event', 'view')->count())->toBe(2);
});
