<?php

use App\Domain\Audit\Activity;
use App\Http\Controllers\RecordFileController;
use App\Models\RecordFile;
use App\Models\ResearchCluster;
use App\Models\ResearchRecord;
use App\Models\ScreeningDecision;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\WorkflowNotice;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
| M3 acceptance (CLAUDE.md §7): each transition only for the right role and owner, every return or
| reject needs a reason, and every transition writes a review row, an activity-log entry and a notice.
*/
beforeEach(function () {
    Storage::fake('research');
    seedBasics();
    $this->student = userWithRole(User::ROLE_STUDENT, ['academic_unit_id' => ceisId(), 'program_id' => bsitId(), 'name' => 'Jasmine Villanueva']);
    $this->adviser = userWithRole(User::ROLE_ADVISER, ['academic_unit_id' => ceisId(), 'name' => 'Prof. Ramon Castillo']);
    $this->coordinator = userWithRole(User::ROLE_COORDINATOR);
    ResearchCluster::where('number', 1)->update(['coordinator_user_id' => $this->coordinator->id]); // CEIS is in Cluster 1
    $this->urdc = userWithRole(User::ROLE_URDC);
});

function submission(array $overrides = [], array $fileOverrides = []): array
{
    $tag = uniqid();

    return array_merge([
        'title' => 'Thesis Title Similarity Screening Using TF-IDF for Capstone Proposals',
        'abstract' => 'This capstone project screens proposed capstone titles against approved studies using TF-IDF and cosine similarity.',
        'research_type' => 'capstone_project',
        'program_id' => bsitId(),
        'adviser_user_id' => test()->adviser->id,
        'year' => 2026,
        'authors' => "Jasmine Villanueva\nCarlo Mendoza",
        'keywords' => 'tf-idf, cosine similarity, title screening',
        'sdgs' => [4, 9],
        'files' => array_merge([
            'abstract' => docxUpload('abstract.docx', "abs {$tag}"),
            'full_paper' => pdfUpload('paper.pdf', "paper {$tag}"),
            'imrad' => pdfUpload('imrad.pdf', "imrad {$tag}"),
            'toa' => pdfUpload('toa.pdf', "toa {$tag}"),
            'references' => docxUpload('refs.docx', "refs {$tag}"),
        ], $fileOverrides),
    ], $overrides);
}

function submitAs(User $student, array $payload = []): Submission
{
    test()->actingAs($student)->post('/student/submit', $payload ?: submission())->assertSessionHasNoErrors();

    return Submission::latest('id')->firstOrFail();
}

it('lets a student submit with all five files', function () {
    Notification::fake();
    $this->actingAs($this->student)->post('/student/submit', submission())
        ->assertSessionHasNoErrors()->assertRedirect();

    $sub = Submission::firstOrFail();
    expect($sub->public_id)->toBe('SUB-0001');
    expect($sub->status)->toBe('submitted');
    expect($sub->current_step)->toBe(1);
    expect($sub->research_cluster_id)->toBe(ResearchCluster::where('number', 1)->value('id'));
    expect($sub->keywords)->toBe(['tf-idf', 'cosine similarity', 'title screening']);
    expect($sub->members()->pluck('name')->all())->toBe(['Jasmine Villanueva', 'Carlo Mendoza']);
    expect(RecordFile::where('submission_id', $sub->id)->count())->toBe(5);
    expect($sub->reviews()->pluck('decision')->all())->toBe(['submit']);
    expect(Activity::where('event', 'submit')->where('subject_id', $sub->id)->exists())->toBeTrue();

    Notification::assertSentTo($this->adviser, WorkflowNotice::class);
    Notification::assertSentTo($this->student, WorkflowNotice::class);
});

it('accepts a missing IMRAD file when marked not applicable', function () {
    $payload = submission(['imrad_not_applicable' => '1']);
    unset($payload['files']['imrad']);
    submitAs($this->student, $payload);

    expect(Submission::first()->imrad_not_applicable)->toBeTrue();
});

it('refuses a submission with a missing required file, bad keywords or a long abstract', function (array $overrides, string $error) {
    $payload = submission($overrides);
    if ($error === 'files.toa') {
        unset($payload['files']['toa']);
    }
    $this->actingAs($this->student)->post('/student/submit', $payload)->assertSessionHasErrors($error);
    expect(Submission::count())->toBe(0);
})->with([
    'no signed TOA' => [[], 'files.toa'],
    'two keywords' => [['keywords' => 'tf-idf, cosine'], 'keywords'],
    'six keywords' => [['keywords' => 'a1, b2, c3, d4, e5, f6'], 'keywords'],
    'abstract over 250 words' => [['abstract' => str_repeat('word ', 251)], 'abstract'],
    'adviser is not an adviser' => [['adviser_user_id' => 999999], 'adviser_user_id'],
]);

it('stops an exact duplicate file and names the record it matches', function () {
    $record = makeRecord();
    $this->actingAs($this->urdc)->post("/urdc/records/{$record->id}/files/full_paper", ['file' => pdfUpload('old.pdf', 'same paper bytes')]);

    $this->actingAs($this->student)->post('/student/submit', submission([], ['full_paper' => pdfUpload('mine.pdf', 'same paper bytes')]))
        ->assertSessionHasErrors(['files.full_paper' => "This exact file is already stored for {$record->public_id}. Upload your own file."]);
    expect(Submission::count())->toBe(0);
});

it('stops a file identical to another student’s submission', function () {
    $first = submitAs($this->student, submission([], ['toa' => pdfUpload('toa.pdf', 'shared toa')]));
    $other = userWithRole(User::ROLE_STUDENT, ['academic_unit_id' => ceisId()]);

    $this->actingAs($other)->post('/student/submit', submission([], ['toa' => pdfUpload('toa.pdf', 'shared toa')]))
        ->assertSessionHasErrors(['files.toa' => "This exact file is already stored for {$first->public_id}. Upload your own file."]);
});

it('lets only the assigned adviser endorse, at the right step', function () {
    Notification::fake();
    $sub = submitAs($this->student);
    $otherAdviser = userWithRole(User::ROLE_ADVISER);

    $this->actingAs($otherAdviser)->post('/adviser/review', ['id' => $sub->public_id, 'decision' => 'endorse'])->assertForbidden();
    $this->actingAs($this->student)->post('/adviser/review', ['id' => $sub->public_id, 'decision' => 'endorse'])->assertForbidden();

    $this->actingAs($this->adviser)->post('/adviser/review', ['id' => $sub->public_id, 'decision' => 'endorse', 'reason' => 'Complete files.'])
        ->assertRedirect('/adviser');
    expect($sub->fresh()->status)->toBe('endorsed');
    expect($sub->fresh()->current_step)->toBe(2);
    Notification::assertSentTo($this->coordinator, WorkflowNotice::class);
    expect(Activity::where('event', 'endorsed')->where('subject_id', $sub->id)->exists())->toBeTrue();

    // Cannot endorse twice.
    $this->actingAs($this->adviser)->post('/adviser/review', ['id' => $sub->public_id, 'decision' => 'endorse'])->assertForbidden();
});

it('needs a reason to return, and tells the student', function () {
    Notification::fake();
    $sub = submitAs($this->student);

    $this->actingAs($this->adviser)->post('/adviser/review', ['id' => $sub->public_id, 'decision' => 'return'])->assertSessionHasErrors('reason');
    expect($sub->fresh()->status)->toBe('submitted');

    $this->post('/adviser/review', ['id' => $sub->public_id, 'decision' => 'return', 'reason' => 'The TOA is unsigned.']);
    expect($sub->fresh()->status)->toBe('returned');
    expect($sub->reviews()->get()->last()->reason)->toBe('The TOA is unsigned.');
    expect(Activity::where('event', 'returned')->where('subject_id', $sub->id)->exists())->toBeTrue();
    Notification::assertSentTo($this->student, WorkflowNotice::class, fn ($n) => str_contains($n->toArray($this->student)['body'], 'The TOA is unsigned.'));
});

it('lets the student revise and resubmit to the adviser, keeping older file versions', function () {
    $sub = submitAs($this->student);
    $this->actingAs($this->adviser)->post('/adviser/review', ['id' => $sub->public_id, 'decision' => 'return', 'reason' => 'Sign the TOA.']);

    $other = userWithRole(User::ROLE_STUDENT);
    $this->actingAs($other)->post('/student/resubmit', ['id' => $sub->public_id])->assertForbidden();

    Notification::fake();
    $this->actingAs($this->student)->post('/student/resubmit', ['id' => $sub->public_id, 'note' => 'Signed.', 'files' => ['toa' => pdfUpload('toa-signed.pdf', 'signed toa v2')]])
        ->assertSessionHasNoErrors();

    $sub->refresh();
    expect($sub->status)->toBe('submitted');
    expect($sub->current_step)->toBe(1);
    expect(RecordFile::where('submission_id', $sub->id)->where('slot', 'toa')->count())->toBe(2);
    expect(RecordFile::where('submission_id', $sub->id)->where('slot', 'toa')->where('is_current', true)->value('original_name'))->toBe('toa-signed.pdf');
    expect($sub->reviews()->get()->last()->decision)->toBe('resubmit');
    Notification::assertSentTo($this->adviser, WorkflowNotice::class);
});

it('lets only the cluster coordinator decide, with a reason unless clearing an original', function () {
    $sub = submitAs($this->student);
    $this->actingAs($this->adviser)->post('/adviser/review', ['id' => $sub->public_id, 'decision' => 'endorse']);

    $otherCoordinator = userWithRole(User::ROLE_COORDINATOR);
    ResearchCluster::where('number', 2)->update(['coordinator_user_id' => $otherCoordinator->id]);
    $this->actingAs($otherCoordinator)->post('/coordinator/screening', ['id' => $sub->public_id, 'decision' => 'clear', 'reason' => 'x'])->assertForbidden();

    // Not screened yet (Sprint 4), so clearing needs a reason.
    $this->actingAs($this->coordinator)->post('/coordinator/screening', ['id' => $sub->public_id, 'decision' => 'clear'])->assertSessionHasErrors('reason');

    Notification::fake();
    $this->post('/coordinator/screening', ['id' => $sub->public_id, 'decision' => 'clear', 'reason' => 'Different scope and data.'])->assertRedirect('/coordinator');
    expect($sub->fresh()->status)->toBe('reviewed');
    expect(ScreeningDecision::where('submission_id', $sub->id)->value('label_for_calibration'))->toBe('distinct');
    Notification::assertSentTo($this->urdc, WorkflowNotice::class);
});

it('allows clearing an original without a reason', function () {
    $sub = submitAs($this->student);
    $this->actingAs($this->adviser)->post('/adviser/review', ['id' => $sub->public_id, 'decision' => 'endorse']);
    $sub->forceFill(['flag' => 'original', 'max_similarity' => 0.21])->save();

    $this->actingAs($this->coordinator)->post('/coordinator/screening', ['id' => $sub->public_id, 'decision' => 'clear'])->assertSessionHasNoErrors();
    expect($sub->fresh()->status)->toBe('reviewed');
});

it('records a rejection as a confirmed duplicate', function () {
    $sub = submitAs($this->student);
    $this->actingAs($this->adviser)->post('/adviser/review', ['id' => $sub->public_id, 'decision' => 'endorse']);
    $this->actingAs($this->coordinator)->post('/coordinator/screening', ['id' => $sub->public_id, 'decision' => 'reject', 'reason' => 'Same study as TUA-2023-1420.']);

    expect($sub->fresh()->status)->toBe('rejected');
    expect(ScreeningDecision::where('submission_id', $sub->id)->value('label_for_calibration'))->toBe('duplicate');
    expect(Activity::where('event', 'rejected')->where('subject_id', $sub->id)->exists())->toBeTrue();
});

it('publishes on URDC approval: record created, files moved, indexed in the catalog', function () {
    $sub = submitAs($this->student);
    $this->actingAs($this->adviser)->post('/adviser/review', ['id' => $sub->public_id, 'decision' => 'endorse']);
    $this->actingAs($this->coordinator)->post('/coordinator/screening', ['id' => $sub->public_id, 'decision' => 'clear', 'reason' => 'Distinct.']);

    $this->actingAs($this->student)->post('/urdc/approvals', ['id' => $sub->public_id, 'decision' => 'approve'])->assertForbidden();

    Notification::fake();
    $this->actingAs($this->urdc)->post('/urdc/approvals', ['id' => $sub->public_id, 'decision' => 'approve'])
        ->assertSessionHasNoErrors()->assertRedirect('/urdc/approvals');

    $sub->refresh();
    $record = ResearchRecord::findOrFail($sub->record_id);
    expect($sub->status)->toBe('published');
    expect($sub->current_step)->toBe(4);
    expect($record->status)->toBe('published');
    expect($record->source)->toBe('submission');
    expect($record->is_complete)->toBeTrue();
    expect($record->title)->toBe($sub->title);
    expect($record->adviser_user_id)->toBe($this->adviser->id);
    expect($record->keywords()->count())->toBe(3);
    expect(RecordFile::where('record_id', $record->id)->count())->toBe(5);
    expect(Activity::where('event', 'published')->where('subject_id', $sub->id)->exists())->toBeTrue();
    Notification::assertSentTo([$this->student, $this->adviser], WorkflowNotice::class);

    $this->get('/catalog?q=Similarity+Screening')->assertSee($sub->title);
});

it('needs a reason for the URDC to return or reject', function (string $decision) {
    $sub = submitAs($this->student);
    $this->actingAs($this->adviser)->post('/adviser/review', ['id' => $sub->public_id, 'decision' => 'endorse']);
    $this->actingAs($this->coordinator)->post('/coordinator/screening', ['id' => $sub->public_id, 'decision' => 'clear', 'reason' => 'Ok.']);

    $this->actingAs($this->urdc)->post('/urdc/approvals', ['id' => $sub->public_id, 'decision' => $decision])->assertSessionHasErrors('reason');
    expect($sub->fresh()->status)->toBe('reviewed');
})->with(['return', 'reject']);

it('shows the workflow to the people in it, and its files only to them', function () {
    $sub = submitAs($this->student);
    $file = RecordFile::where('submission_id', $sub->id)->where('slot', 'full_paper')->firstOrFail();
    $url = RecordFileController::signedUrl($file);

    $this->actingAs($this->student)->get('/student/status?id='.$sub->public_id)->assertOk()->assertSee($sub->title)->assertSee('Submitted');
    $this->actingAs($this->adviser)->get('/adviser/review?id='.$sub->public_id)->assertOk()->assertSee('Endorse');
    $this->actingAs($this->adviser)->get($url)->assertOk();
    $this->actingAs(userWithRole(User::ROLE_ADVISER))->get($url)->assertForbidden();
    $this->actingAs(userWithRole(User::ROLE_STUDENT))->get($url)->assertForbidden();
});

it('keeps in-app copies of the emails and marks them read', function () {
    $sub = submitAs($this->student);

    $this->actingAs($this->adviser)->get('/notifications')->assertOk()->assertSee("New submission to endorse: {$sub->public_id}");
    expect($this->adviser->unreadNotifications()->count())->toBe(1);

    $this->post('/notifications/read')->assertRedirect();
    expect($this->adviser->fresh()->unreadNotifications()->count())->toBe(0);
});
