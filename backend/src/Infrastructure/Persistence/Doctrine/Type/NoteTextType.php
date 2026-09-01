<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Note\ValueObject\NoteText;
use Doctrine\DBAL\Platforms\AbstractPlatform;

final class NoteTextType extends StringValueObjectType
{
    public const string NAME = 'note_text';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    protected function fromDatabase(string $value): NoteText
    {
        return NoteText::fromString($value);
    }

    protected function valueObjectClass(): string
    {
        return NoteText::class;
    }
}
