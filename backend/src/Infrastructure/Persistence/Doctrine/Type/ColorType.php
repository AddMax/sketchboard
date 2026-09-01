<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Note\ValueObject\Color;
use Doctrine\DBAL\Platforms\AbstractPlatform;

final class ColorType extends StringValueObjectType
{
    public const string NAME = 'note_color';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    protected function fromDatabase(string $value): Color
    {
        return Color::fromString($value);
    }

    protected function valueObjectClass(): string
    {
        return Color::class;
    }
}
