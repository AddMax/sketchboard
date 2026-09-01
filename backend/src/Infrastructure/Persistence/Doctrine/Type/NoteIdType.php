<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Note\ValueObject\NoteId;
use Doctrine\DBAL\Platforms\AbstractPlatform;

final class NoteIdType extends StringValueObjectType
{
    public const string NAME = 'note_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    protected function fromDatabase(string $value): NoteId
    {
        return NoteId::fromString($value);
    }

    protected function valueObjectClass(): string
    {
        return NoteId::class;
    }
}
