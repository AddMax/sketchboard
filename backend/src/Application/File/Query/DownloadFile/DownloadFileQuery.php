<?php

declare(strict_types=1);

namespace App\Application\File\Query\DownloadFile;

final readonly class DownloadFileQuery
{
    public function __construct(
        public string $id,
        public ?string $requester = null,
    ) {
    }
}
