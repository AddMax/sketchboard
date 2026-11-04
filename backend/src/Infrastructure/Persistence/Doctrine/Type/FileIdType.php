<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\File\ValueObject\FileId;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * @extends StringValueObjectType<FileId>
 */
final class FileIdType extends StringValueObjectType
{
    public const string NAME = 'file_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    protected function fromDatabase(string $value): FileId
    {
        return FileId::fromString($value);
    }

    protected function valueObjectClass(): string
    {
        return FileId::class;
    }
}
