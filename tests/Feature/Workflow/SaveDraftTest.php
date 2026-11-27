<?php

use App\Domain\Audit\Activity;
use App\Models\RecordFile;
use App\Models\ResearchCluster;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\WorkflowNotice;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/* "Save draft" on Submit a study (CLAUDE.md §7 M3 state "draft"): private, partial, files kept, then submit. */
beforeEach(function () {
    Storage::fake('research');
    seedBasics();
    $this->student = userWithRole(User::ROLE_STUDENT, ['academic_unit_id' => ceisId(), 'program_id' => bsitId(), 'name' => 'Jasmine Villanueva']);
    $this->adviser = userWithRole(User::ROLE_ADVISER, ['academic_unit_id' => ceisId(), 'name' => 'Prof. Ramon Castillo']);
    ResearchCluster::where('number', 1)->update(['coordinator_user_id' => userWithRole(User::ROLE_COORDINATOR)->id]);
});

function draftPayload(array $overrides = []): array
{
    $tag = uniqid();

    return array_merge([
        'action' => 'draft',
        'title' => 'Thesis Title Similarity Screening Using TF-IDF for Capstone Proposals',
        'abstract' => 'This capstone project screens proposed capstone titles against approved studies.',
        'keywords' => 'tf-idf, cosine similarity',
        'files' => ['abstract' => docxUpload('abstract.docx', "abs {$tag}"), 'full_paper' => pdfUpload('paper.pdf', "paper {$tag}")],
    ], $overrides);
}

it('saves a partial draft with its files, privately and without emails', function () {
    Notification::fake();

    $this->actingAs($this->student)->post('/student/submit', draftPayload(['adviser_user_id' => $this->adviser->id]))
        ->assertSessionHasNoErrors()->assertRedirect(route('student.submit'));

    $draft = Submission::firstOrFail();
    expect($draft->status)->toBe('draft')->and($draft->current_step)->toBe(0)->and($draft->submitted_at)->toBeNull()
        ->and($draft->keywords)->toBe(['tf-idf', 'cosine similarity'])->and($draft->research_type)->toBeNull();
    expect(RecordFile::where('submission_id', $draft->id)->where('is_current', true)->pluck('slot')->sort()->values()->all())->toBe(['abstract', 'full_paper']);
    Notification::assertNothingSent();
    expect(Activity::where('event', 'upload')->where('description', "Draft {$draft->public_id} saved with 2 new files")->exists())->toBeTrue();

    // The form comes back filled in, with the saved files marked.
    $this->actingAs($this->student)->get('/student/submit')->assertOk()
        ->assertSee("Draft {$draft->public_id}")->assertSee('Thesis Title Similarity Screening', false)
        ->assertSee('tf-idf, cosine similarity')->assertSee('paper.pdf')->assertDontSee('data-preview', false);

    // Nobody else sees it: not the adviser it names, not the adviser's queue.
    $this->actingAs($this->adviser)->get('/adviser')->assertOk()->assertDontSee($draft->public_id);
    $this->actingAs($this->adviser)->get('/adviser/review?id='.$draft->public_id)->assertOk()->assertDontSee($draft->public_id);
    expect($this->adviser->can('view', $draft))->toBeFalse()->and($this->student->can('view', $draft))->toBeTrue();
    $this->actingAs($this->student)->get('/student/status')->assertOk()->assertDontSee($draft->public_id);
});

it('checks what is filled in, even in a draft', function () {
    $this->actingAs($this->student)->post('/student/submit', draftPayload(['title' => '']))->assertSessionHasErrors('title');
    $this->actingAs($this->student)->post('/student/submit', draftPayload(['keywords' => 'a, b, c, d, e, f']))->assertSessionHasErrors('keywords');
    $this->actingAs($this->student)->post('/student/submit', draftPayload(['files' => ['full_paper' => docxUpload('paper.docx')]]))->assertSessionHasErrors('files.full_paper');
    expect(Submission::count())->toBe(0);
});

it('submits the draft, using the saved files, and only then tells the adviser', function () {
    Notification::fake();
    $this->actingAs($this->student)->post('/student/submit', draftPayload())->assertSessionHasNoErrors();
    $draft = Submission::firstOrFail();

    // Missing metadata and the three files not saved yet are still required on submit.
    $this->actingAs($this->student)->post('/student/submit', ['title' => $draft->title])->assertSessionHasErrors(['abstract', 'research_type', 'adviser_user_id', 'keywords']);

    $tag = uniqid();
    $complete = [
        'title' => $draft->title, 'abstract' => $draft->abstract, 'research_type' => 'capstone_project', 'program_id' => bsitId(),
        'adviser_user_id' => $this->adviser->id, 'year' => 2026, 'authors' => "Jasmine Villanueva\nCarlo Mendoza",
        'keywords' => 'tf-idf, cosine similarity, title screening', 'sdgs' => [4],
    ];
    $this->actingAs($this->student)->post('/student/submit', $complete + ['files' => ['imrad' => pdfUpload('imrad.pdf', "i {$tag}")]])
        ->assertSessionHasErrors(['files.toa', 'files.references']);
    expect($draft->fresh()->status)->toBe('draft');

    $this->actingAs($this->student)->post('/student/submit', $complete + ['files' => [
        'imrad' => pdfUpload('imrad.pdf', "i {$tag}"), 'toa' => pdfUpload('toa.pdf', "t {$tag}"), 'references' => docxUpload('refs.docx', "r {$tag}"),
    ]])->assertSessionHasNoErrors()->assertRedirect();

    $sub = $draft->fresh();
    expect(Submission::count())->toBe(1)->and($sub->public_id)->toBe($draft->public_id)
        ->and($sub->status)->toBe('submitted')->and($sub->current_step)->toBe(1)->and($sub->submitted_at)->not->toBeNull()
        ->and($sub->members()->count())->toBe(2)
        ->and(RecordFile::where('submission_id', $sub->id)->where('is_current', true)->count())->toBe(5);
    Notification::assertSentTo($this->adviser, WorkflowNotice::class);
    expect(Activity::where('event', 'submit')->where('description', "Submitted {$sub->public_id} with 5 files (from a saved draft)")->exists())->toBeTrue();
});

it('deletes a draft and its files', function () {
    $this->actingAs($this->student)->post('/student/submit', draftPayload())->assertSessionHasNoErrors();
    $draft = Submission::firstOrFail();
    $paths = RecordFile::where('submission_id', $draft->id)->pluck('path')->all();

    $this->actingAs($this->student)->post('/student/submit/draft/discard')->assertRedirect(route('student.submit'));

    expect(Submission::count())->toBe(0)->and(RecordFile::count())->toBe(0);
    foreach ($paths as $p) {
        Storage::disk('research')->assertMissing($p);
    }
    $this->actingAs($this->student)->post('/student/submit/draft/discard')->assertNotFound();
});
