<?php

declare(strict_types=1);

namespace App\Application\File\Query\GetFile;

final readonly class GetFileQuery
{
    public function __construct(
        public string $id,
        public ?string $requester = null,
    ) {
    }
}
