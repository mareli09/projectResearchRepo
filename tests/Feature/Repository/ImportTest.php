<?php

use App\Domain\Audit\Activity;
use App\Domain\Repository\Import\ImportTemplate;
use App\Models\RecordFile;
use App\Models\ResearchRecord;
use App\Models\User;
use Database\Seeders\Support\SampleDocuments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    seedBasics();
    Storage::fake('research');
    Storage::fake('local');
    $this->actingAs(userWithRole(User::ROLE_URDC));
});

function importRow(array $overrides = []): array
{
    return array_merge([
        'title' => 'Flood Risk Prediction for Barangays in Quezon City Using Random Forest',
        'abstract' => 'This capstone project built a model that predicts flood risk for each barangay.',
        'authors' => 'Francine Garcia; Enzo Navarro',
        'adviser' => 'Prof. Ramon Castillo',
        'college' => 'CEIS',
        'program' => 'BS Information Technology',
        'research_type' => 'Capstone project',
        'degree_level' => '',
        'year' => '2025',
        'keywords' => 'flood risk; random forest; prediction',
        'sdgs' => '11, 13',
        'source' => 'Google Form migration',
        'abstract_file' => '', 'full_paper_file' => '', 'imrad_file' => '', 'toa_file' => '', 'references_file' => '',
    ], $overrides);
}

/** @param list<array<string, string>> $rows */
function csvUpload(array $rows): UploadedFile
{
    $out = fopen('php://temp', 'r+');
    fputcsv($out, array_keys(ImportTemplate::COLUMNS));
    foreach ($rows as $r) {
        fputcsv($out, array_map(fn ($c) => $r[$c] ?? '', array_keys(ImportTemplate::COLUMNS)));
    }
    rewind($out);

    return UploadedFile::fake()->createWithContent('google-form.csv', stream_get_contents($out));
}

/** @param array<string, string> $files name => bytes */
function zipUpload(array $files): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    foreach ($files as $name => $bytes) {
        $zip->addFromString($name, $bytes);
    }
    $zip->close();

    return UploadedFile::fake()->createWithContent('files.zip', file_get_contents($path));
}

function previewToken($response): string
{
    preg_match('#/urdc/import/([0-9a-f-]{36})#', $response->getContent(), $m);

    return $m[1];
}

it('offers the template as CSV and XLSX', function () {
    $csv = $this->get('/urdc/import/template.csv')->assertOk();
    expect($csv->streamedContent())->toContain('title,abstract,authors');
    $this->get('/urdc/import/template.xlsx')->assertOk()->assertDownload('tua-import-template.xlsx');
});

it('is only for the URDC', function (string $role) {
    $this->actingAs(userWithRole($role))->get('/urdc/import')->assertForbidden();
})->with([User::ROLE_STUDENT, User::ROLE_ADVISER, User::ROLE_COORDINATOR, User::ROLE_SYSADMIN]);

it('dry-runs a file: row-level errors and duplicates, nothing written', function () {
    makeRecord(['title' => 'Existing Study on Hand Hygiene Compliance']);

    $response = $this->post('/urdc/import/preview', ['sheet' => csvUpload([
        importRow(),
        importRow(['title' => 'Second Good Row About Telemedicine', 'college' => 'XYZ']),
        importRow(['title' => 'Too Few Keywords Row', 'keywords' => 'one; two']),
        importRow(['title' => 'existing study on hand-hygiene compliance!']), // duplicate of the repository
        importRow(['title' => 'Flood risk prediction for barangays in Quezon City using Random Forest']), // duplicate of row 2
        importRow(['title' => 'Bad SDG Row', 'sdgs' => '19']),
    ])])->assertOk();

    $response->assertSee('Ready to import')
        ->assertSee('Unknown college &quot;XYZ&quot;.', false)
        ->assertSee('Add 3 to 5 keywords.')
        ->assertSee('Same title as TUA-')
        ->assertSee('Same title as row 2.')
        ->assertSee('SDGs are numbers from 1 to 17.');
    expect(ResearchRecord::count())->toBe(1); // only the existing one
});

it('imports the ready rows, skips the rest, and logs the import', function () {
    $response = $this->post('/urdc/import/preview', ['sheet' => csvUpload([
        importRow(),
        importRow(['title' => 'Mobile Attendance Monitoring With QR Codes', 'research_type' => 'faculty research', 'source' => 'Legacy import']),
        importRow(['title' => 'Broken Row', 'year' => 'next year']),
    ])]);

    $this->post('/urdc/import/'.previewToken($response), ['publish_complete' => '1'])->assertRedirect('/urdc/records');

    expect(ResearchRecord::count())->toBe(2);
    $flood = ResearchRecord::where('title', 'like', 'Flood Risk%')->firstOrFail();
    expect($flood->source)->toBe('google_form_migration');
    expect($flood->degree_level)->toBe('undergraduate');
    expect($flood->keywords()->pluck('term')->sort()->values()->all())->toBe(['flood risk', 'prediction', 'random forest']);
    expect($flood->authors()->pluck('name')->all())->toBe(['Francine Garcia', 'Enzo Navarro']);
    expect($flood->status)->toBe('draft'); // no files, so not complete

    $mobile = ResearchRecord::where('title', 'like', 'Mobile%')->firstOrFail();
    expect($mobile->source)->toBe('legacy_import');
    expect($mobile->degree_level)->toBe('faculty');

    $log = Activity::where('event', 'import')->firstOrFail();
    expect($log->properties['details']['imported'])->toBe(2);
    expect($log->properties['details']['skipped'])->toBe(1);
});

it('attaches files from the ZIP, checks them, and publishes complete records', function () {
    $zip = zipUpload([
        'flood-abstract.docx' => SampleDocuments::docx('abstract'),
        'flood-paper.pdf' => SampleDocuments::pdf('paper'),
        'flood-toa.pdf' => SampleDocuments::pdf('toa'),
        'flood-refs.docx' => SampleDocuments::docx('refs'),
        'fake.pdf' => 'not really a pdf',
    ]);
    $rows = [
        importRow(['abstract_file' => 'flood-abstract.docx', 'full_paper_file' => 'flood-paper.pdf', 'imrad_file' => 'N/A', 'toa_file' => 'flood-toa.pdf', 'references_file' => 'flood-refs.docx']),
        importRow(['title' => 'Row With A Fake PDF', 'full_paper_file' => 'fake.pdf']),
        importRow(['title' => 'Row With A Missing File', 'toa_file' => 'nowhere.pdf']),
    ];

    $response = $this->post('/urdc/import/preview', ['sheet' => csvUpload($rows), 'files_zip' => $zip])
        ->assertSee('The file content is not a real .pdf file.')
        ->assertSee('File &quot;nowhere.pdf&quot; is not in the ZIP.', false);

    $this->post('/urdc/import/'.previewToken($response), ['publish_complete' => '1']);

    $flood = ResearchRecord::firstOrFail();
    expect(ResearchRecord::count())->toBe(1);
    expect($flood->imrad_not_applicable)->toBeTrue();
    expect($flood->is_complete)->toBeTrue();
    expect($flood->status)->toBe('published');
    expect(RecordFile::where('record_id', $flood->id)->count())->toBe(4);
    expect(Storage::disk('local')->allFiles('imports'))->toBe([]); // temporary upload removed
});

it('detects duplicates by file hash, against the repository and within the ZIP', function () {
    $existing = makeRecord();
    $this->post("/urdc/records/{$existing->id}/files/full_paper", ['file' => pdfUpload('old.pdf', 'identical paper')]);

    $zip = zipUpload([
        'same-as-repo.pdf' => SampleDocuments::pdf('identical paper'),
        'twice-a.pdf' => SampleDocuments::pdf('twice'),
        'twice-b.pdf' => SampleDocuments::pdf('twice'),
    ]);
    $response = $this->post('/urdc/import/preview', ['sheet' => csvUpload([
        importRow(['title' => 'Row One Matches The Repository', 'full_paper_file' => 'same-as-repo.pdf']),
        importRow(['title' => 'Row Two Is Fine', 'full_paper_file' => 'twice-a.pdf']),
        importRow(['title' => 'Row Three Repeats Row Two', 'full_paper_file' => 'twice-b.pdf']),
    ]), 'files_zip' => $zip]);

    $response->assertSee("is identical to a file of {$existing->public_id}.", false)
        ->assertSee('is identical to a file in row 3.', false);
});

it('reads XLSX files', function () {
    $sheet = new Spreadsheet;
    $sheet->getActiveSheet()->fromArray([array_keys(ImportTemplate::COLUMNS), array_values(importRow(['title' => 'From An XLSX File']))]);
    $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    (new Xlsx($sheet))->save($path);

    $response = $this->post('/urdc/import/preview', ['sheet' => UploadedFile::fake()->createWithContent('form.xlsx', file_get_contents($path))])
        ->assertOk()->assertSee('From An XLSX File')->assertSee('Ready');
    $this->post('/urdc/import/'.previewToken($response));
    expect(ResearchRecord::where('title', 'From An XLSX File')->exists())->toBeTrue();
});

it('explains a file with missing columns', function () {
    $bad = UploadedFile::fake()->createWithContent('bad.csv', "name,notes\nA,B\n");
    $this->post('/urdc/import/preview', ['sheet' => $bad])->assertRedirect('/urdc/import')->assertSessionHasErrors('sheet');
});
