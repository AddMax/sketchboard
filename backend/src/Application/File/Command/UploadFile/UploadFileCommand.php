<?php

declare(strict_types=1);

namespace App\Application\File\Command\UploadFile;

/**
 * Загрузка файла в примитивах транспорта. Размер не передаётся:
 * его считает домен по содержимому, заявленному числу верить нельзя.
 */
final readonly class UploadFileCommand
{
    public function __construct(
        public string $name,
        public string $mimeType,
        public string $contents,
        public string $visibility,
        public ?string $owner = null,
    ) {
    }
}
