<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Note\ValueObject\Author;
use Doctrine\DBAL\Platforms\AbstractPlatform;

final class AuthorType extends StringValueObjectType
{
    public const string NAME = 'note_author';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    protected function fromDatabase(string $value): Author
    {
        return Author::fromString($value);
    }

    protected function valueObjectClass(): string
    {
        return Author::class;
    }
}
