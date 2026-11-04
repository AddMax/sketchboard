<?php

declare(strict_types=1);

namespace App\Domain\File\ValueObject;

use App\Domain\Shared\InvalidArgument;
use Symfony\Component\Uid\Uuid;

/**
 * Идентификатор файла. UUID v7, как и у заметки: монотонный во времени,
 * а значит, и ключи в хранилище ложатся по порядку загрузки.
 */
final readonly class FileId implements \Stringable
{
    private function __construct(private string $value)
    {
    }

    public static function generate(): self
    {
        return new self(Uuid::v7()->toRfc4122());
    }

    public static function fromString(string $value): self
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidArgument(sprintf('«%s» не является корректным идентификатором файла', $value), 'id');
        }

        return new self(Uuid::fromString($value)->toRfc4122());
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
