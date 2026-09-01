<?php

declare(strict_types=1);

namespace App\Domain\Note\ValueObject;

use App\Domain\Shared\InvalidArgument;

final readonly class Author implements \Stringable
{
    public const int MAX_LENGTH = 64;

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ('' === $value) {
            throw new InvalidArgument('Автор не может быть пустым', 'author');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidArgument(
                sprintf('Имя автора длиннее %d символов', self::MAX_LENGTH),
                'author',
            );
        }

        return new self($value);
    }

    public static function anonymous(): self
    {
        return new self('anonymous');
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
