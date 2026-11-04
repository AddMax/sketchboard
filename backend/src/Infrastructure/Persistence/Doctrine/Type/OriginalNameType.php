<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\File\ValueObject\OriginalName;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * @extends StringValueObjectType<OriginalName>
 */
final class OriginalNameType extends StringValueObjectType
{
    public const string NAME = 'file_name';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    protected function fromDatabase(string $value): OriginalName
    {
        return OriginalName::fromString($value);
    }

    protected function valueObjectClass(): string
    {
        return OriginalName::class;
    }
}
