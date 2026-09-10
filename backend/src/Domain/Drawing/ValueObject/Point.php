<?php

declare(strict_types=1);

namespace App\Domain\Drawing\ValueObject;

use App\Domain\Shared\InvalidArgument;

/**
 * Точка в виртуальном пространстве доски для рисования. Полотно бесконечно,
 * поэтому границ нет — но координата обязана быть конечным числом:
 * NaN и бесконечность в JSON не сериализуются и ломают отрисовку.
 */
final readonly class Point
{
    private function __construct(
        private float $x,
        private float $y,
    ) {
    }

    public static function at(float $x, float $y): self
    {
        if (!is_finite($x) || !is_finite($y)) {
            throw new InvalidArgument('Координаты точки должны быть конечными числами', 'lines');
        }

        return new self($x, $y);
    }

    /**
     * @param mixed $data ожидается {x: number, y: number}
     */
    public static function fromArray(mixed $data): self
    {
        if (!\is_array($data) || !is_numeric($data['x'] ?? null) || !is_numeric($data['y'] ?? null)) {
            throw new InvalidArgument('Точка задаётся объектом {x, y} с числовыми координатами', 'lines');
        }

        return self::at((float) $data['x'], (float) $data['y']);
    }

    public function x(): float
    {
        return $this->x;
    }

    public function y(): float
    {
        return $this->y;
    }

    /**
     * @return array{x: float, y: float}
     */
    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y];
    }
}
