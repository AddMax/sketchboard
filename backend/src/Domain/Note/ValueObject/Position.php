<?php

declare(strict_types=1);

namespace App\Domain\Note\ValueObject;

use App\Domain\Shared\InvalidArgument;

/**
 * Координаты на доске. Границы — правило домена: за пределы полотна
 * заметку вынести нельзя.
 */
final readonly class Position
{
    public const int MIN = 0;
    public const int MAX = 10_000;

    private function __construct(
        private int $x,
        private int $y,
    ) {
    }

    public static function at(int $x, int $y): self
    {
        self::assertWithinBoard($x, 'x');
        self::assertWithinBoard($y, 'y');

        return new self($x, $y);
    }

    public function x(): int
    {
        return $this->x;
    }

    public function y(): int
    {
        return $this->y;
    }

    public function equals(self $other): bool
    {
        return $this->x === $other->x && $this->y === $other->y;
    }

    private static function assertWithinBoard(int $value, string $axis): void
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw new InvalidArgument(
                sprintf('Координата %s должна быть в диапазоне %d…%d', $axis, self::MIN, self::MAX),
                $axis,
            );
        }
    }
}
