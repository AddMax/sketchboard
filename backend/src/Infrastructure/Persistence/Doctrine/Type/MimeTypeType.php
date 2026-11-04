<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\File\ValueObject\MimeType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * @extends StringValueObjectType<MimeType>
 */
final class MimeTypeType extends StringValueObjectType
{
    public const string NAME = 'file_mime_type';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    protected function fromDatabase(string $value): MimeType
    {
        return MimeType::fromString($value);
    }

    protected function valueObjectClass(): string
    {
        return MimeType::class;
    }
}
