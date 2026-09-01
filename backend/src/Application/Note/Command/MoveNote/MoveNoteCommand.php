<?php

declare(strict_types=1);

namespace App\Application\Note\Command\MoveNote;

final readonly class MoveNoteCommand
{
    public function __construct(
        public string $id,
        public int $x,
        public int $y,
    ) {
    }
}
