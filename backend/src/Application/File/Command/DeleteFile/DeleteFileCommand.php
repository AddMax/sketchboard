<?php

declare(strict_types=1);

namespace App\Application\File\Command\DeleteFile;

final readonly class DeleteFileCommand
{
    public function __construct(
        public string $id,
        public ?string $requester = null,
    ) {
    }
}
