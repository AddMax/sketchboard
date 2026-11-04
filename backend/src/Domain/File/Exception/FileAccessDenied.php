<?php

declare(strict_types=1);

namespace App\Domain\File\Exception;

use App\Domain\File\ValueObject\FileId;

/**
 * Файл есть, но читать его этому участнику нельзя. Отдельный тип, а не
 * FileNotFound: прятать существование файла здесь незачем, а клиенту
 * полезно отличать «нет» от «не ваш».
 */
final class FileAccessDenied extends \DomainException
{
    public static function toFile(FileId $id): self
    {
        return new self(sprintf('Файл %s приватный и доступен только владельцу', $id->toString()));
    }
}
