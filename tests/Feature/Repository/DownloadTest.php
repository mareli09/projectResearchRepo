<?php

use App\Domain\Audit\Activity;
use App\Http\Controllers\RecordFileController;
use App\Models\DownloadLog;
use App\Models\RecordFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedBasics();
    Storage::fake('research');
    $this->record = makeRecord();
    $this->actingAs(userWithRole(User::ROLE_URDC));
    $this->post("/urdc/records/{$this->record->id}/files/full_paper", ['file' => pdfUpload('Paper.pdf', 'download me')]);
    $this->post("/urdc/records/{$this->record->id}/files/toa", ['file' => pdfUpload('TOA.pdf', 'agreement')]);
    $this->paper = RecordFile::where('slot', 'full_paper')->firstOrFail();
    $this->toa = RecordFile::where('slot', 'toa')->firstOrFail();
});

it('lets the URDC download through a signed link, and logs it', function () {
    $this->get(RecordFileController::signedUrl($this->paper))->assertOk()->assertDownload('Paper.pdf');

    expect(DownloadLog::where('record_file_id', $this->paper->id)->count())->toBe(1);
    expect($this->record->fresh()->downloads)->toBe(1);
    expect(Activity::where('event', 'download')->where('subject_id', $this->record->id)->exists())->toBeTrue();
});

it('refuses an unsigned or expired link', function () {
    $this->get("/files/{$this->paper->id}")->assertForbidden();

    $url = RecordFileController::signedUrl($this->paper);
    $this->travel(11)->minutes();
    $this->get($url)->assertForbidden();
    expect(DownloadLog::count())->toBe(0);
});

it('refuses guests', function () {
    auth()->logout();
    $this->get(RecordFileController::signedUrl($this->paper))->assertRedirect(route('login'));
});

it('checks permissions on every download', function (string $role, bool $paperAllowed) {
    $this->record->forceFill(['status' => 'published'])->save();
    $this->actingAs(userWithRole($role));

    $this->get(RecordFileController::signedUrl($this->paper))->assertStatus($paperAllowed ? 200 : 403);
    // The signed Transfer of Ownership Agreement is for the URDC only.
    $this->get(RecordFileController::signedUrl($this->toa))->assertForbidden();
})->with([
    'student (needs an approved request, Sprint 5)' => [User::ROLE_STUDENT, false],
    'faculty adviser' => [User::ROLE_ADVISER, true],
    'research coordinator' => [User::ROLE_COORDINATOR, true],
    'system administrator' => [User::ROLE_SYSADMIN, false],
]);

it('does not let advisers download files of unpublished records', function () {
    $this->actingAs(userWithRole(User::ROLE_ADVISER));
    $this->get(RecordFileController::signedUrl($this->paper))->assertForbidden();
});
