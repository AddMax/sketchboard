<?php

declare(strict_types=1);

namespace App\Domain\Note\ValueObject;

use App\Domain\Shared\InvalidArgument;

final readonly class NoteText implements \Stringable
{
    public const int MAX_LENGTH = 2000;

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ('' === $value) {
            throw new InvalidArgument('Текст заметки не может быть пустым', 'text');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidArgument(
                sprintf('Текст заметки длиннее %d символов', self::MAX_LENGTH),
                'text',
            );
        }

        return new self($value);
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
