<?php

declare(strict_types=1);

namespace App\Application\File\ReadModel;

/**
 * Байты файла с тем, что нужно, чтобы их отдать: тип для Content-Type
 * и имя для Content-Disposition. В JSON не превращается — это не ответ API,
 * а тело ответа.
 */
final readonly class FileContent
{
    public function __construct(
        public string $name,
        public string $mimeType,
        public string $contents,
    ) {
    }
}
