<?php

declare(strict_types=1);

namespace App\Domain\File\Exception;

use App\Domain\File\ValueObject\FileId;

final class FileNotFound extends \DomainException
{
    public static function withId(FileId $id): self
    {
        return new self(sprintf('Файл %s не найден', $id->toString()));
    }
}
