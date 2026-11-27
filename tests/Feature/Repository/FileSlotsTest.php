<?php

use App\Domain\Audit\Activity;
use App\Models\RecordFile;
use App\Models\ResearchRecord;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\Support\SampleDocuments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedBasics();
    Storage::fake('research');
    $this->urdc = userWithRole(User::ROLE_URDC);
    $this->actingAs($this->urdc);
});

/** Upload all required files to a record. */
function fillSlots(ResearchRecord $r, string $tag = 'a'): void
{
    test()->post("/urdc/records/{$r->id}/files/abstract", ['file' => docxUpload('abstract.docx', "abs {$tag}")]);
    test()->post("/urdc/records/{$r->id}/files/full_paper", ['file' => pdfUpload('paper.pdf', "paper {$tag}")]);
    test()->post("/urdc/records/{$r->id}/files/imrad", ['file' => pdfUpload('imrad.pdf', "imrad {$tag}")]);
    test()->post("/urdc/records/{$r->id}/files/toa", ['file' => pdfUpload('toa.pdf', "toa {$tag}")]);
    test()->post("/urdc/records/{$r->id}/files/references", ['file' => docxUpload('refs.docx', "refs {$tag}")]);
}

it('stores a file privately with its SHA-256 and logs the upload', function () {
    $r = makeRecord();
    $upload = pdfUpload('Final paper.pdf', 'the paper');
    $sha = hash_file('sha256', $upload->getRealPath());

    $this->post("/urdc/records/{$r->id}/files/full_paper", ['file' => $upload])->assertSessionHasNoErrors();

    $file = RecordFile::where('record_id', $r->id)->where('slot', 'full_paper')->firstOrFail();
    expect($file->sha256)->toBe($sha);
    expect($file->original_name)->toBe('Final paper.pdf');
    expect($file->mime)->toBe('application/pdf');
    Storage::disk('research')->assertExists($file->path);
    expect($file->path)->not->toStartWith('public');
    expect(Activity::where('event', 'upload')->where('subject_id', $r->id)->exists())->toBeTrue();
});

it('keeps research files outside the public web root', function () {
    expect(config('filesystems.disks.research.root'))->toBe(storage_path('app/research'));
    expect(config('filesystems.disks.research.serve'))->toBeFalse();
});

it('checks the extension for each slot', function () {
    $r = makeRecord();
    $this->post("/urdc/records/{$r->id}/files/full_paper", ['file' => docxUpload('paper.docx')])
        ->assertSessionHasErrors(['file_full_paper' => 'Full paper must be a .pdf file.']);
    $this->post("/urdc/records/{$r->id}/files/abstract", ['file' => pdfUpload('abstract.pdf')])->assertSessionHasErrors('file_abstract');
});

it('checks the real MIME type, not only the extension', function () {
    $r = makeRecord();
    $fake = UploadedFile::fake()->createWithContent('paper.pdf', 'This is plain text pretending to be a PDF.');
    $this->post("/urdc/records/{$r->id}/files/full_paper", ['file' => $fake])
        ->assertSessionHasErrors(['file_full_paper' => 'The file content is not a real .pdf file.']);

    // A ZIP renamed to .docx is not a Word document.
    $zipPath = tempnam(sys_get_temp_dir(), 'z');
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::OVERWRITE);
    $zip->addFromString('readme.txt', 'not word');
    $zip->close();
    $this->post("/urdc/records/{$r->id}/files/abstract", ['file' => UploadedFile::fake()->createWithContent('abstract.docx', file_get_contents($zipPath))])
        ->assertSessionHasErrors(['file_abstract' => 'The file content is not a real .docx file.']);

    expect(RecordFile::count())->toBe(0);
});

it('enforces the maximum file size from Settings', function () {
    Setting::updateOrCreate(['key' => 'files.max_mb'], ['value' => 1]);
    $r = makeRecord();
    $big = UploadedFile::fake()->createWithContent('paper.pdf', SampleDocuments::pdf(str_repeat('x', 1_100_000)));

    $this->post("/urdc/records/{$r->id}/files/full_paper", ['file' => $big])->assertSessionHasErrors(['file_full_paper' => 'The file is larger than 1 MB.']);
});

it('stops an exact duplicate file and names the record it matches', function () {
    $first = makeRecord();
    $second = makeRecord();
    $this->post("/urdc/records/{$first->id}/files/full_paper", ['file' => pdfUpload('a.pdf', 'same bytes')]);

    $this->post("/urdc/records/{$second->id}/files/full_paper", ['file' => pdfUpload('b.pdf', 'same bytes')])
        ->assertSessionHasErrors(['file_full_paper' => "This exact file is already stored for {$first->public_id}."]);
    expect(RecordFile::where('record_id', $second->id)->count())->toBe(0);
});

it('keeps older versions when a new file is uploaded', function () {
    $r = makeRecord();
    $this->post("/urdc/records/{$r->id}/files/full_paper", ['file' => pdfUpload('v1.pdf', 'version 1')]);
    $this->post("/urdc/records/{$r->id}/files/full_paper", ['file' => pdfUpload('v2.pdf', 'version 2')]);

    $files = RecordFile::where('record_id', $r->id)->where('slot', 'full_paper')->orderBy('id')->get();
    expect($files)->toHaveCount(2);
    expect($files[0]->is_current)->toBeFalse();
    expect($files[1]->is_current)->toBeTrue();
    expect($files[0]->sha256)->not->toBe($files[1]->sha256);
    Storage::disk('research')->assertExists($files[0]->path);
});

it('cannot publish without the required slots', function () {
    $r = makeRecord();
    $this->post("/urdc/records/{$r->id}/files/full_paper", ['file' => pdfUpload()]);

    $this->post("/urdc/records/{$r->id}/publish")->assertSessionHasErrors('publish');
    expect($r->fresh()->status)->toBe('draft');
    $this->get("/urdc/records/{$r->id}")->assertSee('Missing file: Signed Transfer of Ownership Agreement (R-12-01).');
});

it('publishes once all required slots are filled, and logs it', function () {
    $r = makeRecord();
    fillSlots($r);
    expect($r->fresh()->is_complete)->toBeTrue();

    $this->post("/urdc/records/{$r->id}/publish")->assertSessionHasNoErrors();
    expect($r->fresh()->status)->toBe('published');
    expect($r->fresh()->published_at)->not->toBeNull();
    expect(Activity::where('event', 'published')->where('subject_id', $r->id)->exists())->toBeTrue();
});

it('lets the URDC mark IMRAD not applicable', function () {
    $r = makeRecord();
    foreach (['abstract' => docxUpload(), 'full_paper' => pdfUpload('p.pdf', 'p'), 'toa' => pdfUpload('t.pdf', 't'), 'references' => docxUpload('r.docx', 'r')] as $slot => $f) {
        $this->post("/urdc/records/{$r->id}/files/{$slot}", ['file' => $f]);
    }
    expect($r->fresh()->is_complete)->toBeFalse();

    $this->post("/urdc/records/{$r->id}/imrad", ['not_applicable' => 1]);
    expect($r->fresh()->is_complete)->toBeTrue();
    $this->post("/urdc/records/{$r->id}/publish")->assertSessionHasNoErrors();
});

it('refuses to publish with too few keywords', function () {
    $r = makeRecord();
    fillSlots($r, 'kw');
    $r->keywords()->detach();

    $this->post("/urdc/records/{$r->id}/publish")->assertSessionHasErrors('publish');
    expect($r->fresh()->status)->toBe('draft');
});
