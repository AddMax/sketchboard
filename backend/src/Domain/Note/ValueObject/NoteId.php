<?php

declare(strict_types=1);

namespace App\Domain\Note\ValueObject;

use App\Domain\Shared\InvalidArgument;
use Symfony\Component\Uid\Uuid;

/**
 * Идентификатор заметки. UUID v7 монотонно растёт во времени —
 * записи ложатся в индекс по порядку создания.
 */
final readonly class NoteId implements \Stringable
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
            throw new InvalidArgument(sprintf('«%s» не является корректным идентификатором', $value), 'id');
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
