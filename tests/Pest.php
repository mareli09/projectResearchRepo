<?php

use App\Domain\Repository\RecordRules;
use App\Domain\Repository\RecordWriter;
use App\Models\AcademicUnit;
use App\Models\Program;
use App\Models\ResearchRecord;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\Support\SampleDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
| Feature tests run against the tua_repository_test MariaDB/MySQL database
| (see phpunit.xml) so FULLTEXT and enum behaviour match production.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/** Reference data and roles, without demo users. */
function seedBasics(): void
{
    test()->seed([ReferenceDataSeeder::class, RoleSeeder::class]);
}

/** An active user with one role. */
function userWithRole(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

function ceisId(): int
{
    return (int) AcademicUnit::where('code', 'CEIS')->value('id');
}

function bsitId(): int
{
    return (int) Program::where('name', 'BS Information Technology')->value('id');
}

/** A valid draft record through the real writer. */
function makeRecord(array $overrides = []): ResearchRecord
{
    $data = RecordRules::normalize(array_merge([
        'title' => 'Flood Risk Prediction for Barangays Using Random Forest '.Str::random(6),
        'abstract' => 'This study predicts flood risk for each barangay using rainfall and elevation data.',
        'authors' => "Francine Garcia\nEnzo Navarro",
        'adviser_name' => 'Prof. Ramon Castillo',
        'academic_unit_id' => ceisId(),
        'program_id' => bsitId(),
        'research_type' => 'capstone_project',
        'degree_level' => 'undergraduate',
        'year' => 2025,
        'keywords' => 'flood risk, random forest, prediction',
        'sdgs' => [11, 13],
    ], $overrides));

    return app(RecordWriter::class)->create($data, 'legacy_import');
}

/** Real small files that pass the MIME and structure checks. */
function pdfUpload(string $name = 'paper.pdf', string $text = 'sample'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, SampleDocuments::pdf($text));
}

function docxUpload(string $name = 'abstract.docx', string $text = 'sample'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, SampleDocuments::docx($text));
}

/** Reads a private or protected property of a notification (tokens and codes are private). */
function secretOf(Notification $notification, string $property): string
{
    return (fn () => $this->{$property})->call($notification);
}
