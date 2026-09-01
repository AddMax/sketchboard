<?php

declare(strict_types=1);

namespace App\Domain\Note\ValueObject;

use App\Domain\Shared\InvalidArgument;

final readonly class Color implements \Stringable
{
    private const string PATTERN = '/^#[0-9a-f]{6}$/';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = strtolower(trim($value));

        if (1 !== preg_match(self::PATTERN, $value)) {
            throw new InvalidArgument('Цвет задаётся в формате #rrggbb', 'color');
        }

        return new self($value);
    }

    public static function default(): self
    {
        return new self('#ffd166');
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
