<?php

declare(strict_types=1);

namespace App\Application\Note\Command\DeleteNote;

final readonly class DeleteNoteCommand
{
    public function __construct(public string $id)
    {
    }
}
