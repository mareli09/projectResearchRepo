<?php

namespace App\Domain\Repository;

use RuntimeException;

/** An identical file (same SHA-256) is already stored for another record (CLAUDE.md §1 rule 6). */
class DuplicateFileException extends RuntimeException
{
    public function __construct(public readonly string $matchingPublicId, public readonly string $slot)
    {
        parent::__construct("This exact file is already stored for {$matchingPublicId}.");
    }
}
