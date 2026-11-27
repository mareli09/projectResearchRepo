<?php

namespace App\Domain\Repository\Import;

/** The check result for one spreadsheet row. */
final class ImportRow
{
    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> */
    public array $duplicates = [];

    /** @var list<string> */
    public array $warnings = [];

    /** @var array<string, mixed> normalized record data */
    public array $data = [];

    /** @var array<string, string> slot => entry name inside the ZIP */
    public array $files = [];

    public bool $imradNotApplicable = false;

    public string $source = 'google_form_migration';

    /** @param  array<string, mixed>  $raw */
    public function __construct(public readonly int $line, public readonly array $raw) {}

    public function status(): string
    {
        return match (true) {
            $this->errors !== [] => 'error',
            $this->duplicates !== [] => 'duplicate',
            default => 'ready',
        };
    }

    public function isReady(): bool
    {
        return $this->status() === 'ready';
    }
}
