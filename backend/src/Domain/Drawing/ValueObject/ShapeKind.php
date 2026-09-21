<?php

declare(strict_types=1);

namespace App\Domain\Drawing\ValueObject;

use App\Domain\Shared\InvalidArgument;

/**
 * Виды фигур. Каждая описывается одним ограничивающим прямоугольником:
 * эллипс вписан в него, треугольник стоит на его нижней стороне.
 */
enum ShapeKind: string
{
    case Rect = 'rect';
    case Ellipse = 'ellipse';
    case Triangle = 'triangle';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw new InvalidArgument(
            sprintf('Неизвестный вид фигуры «%s»; допустимы: %s', $value, implode(', ', array_column(self::cases(), 'value'))),
            'elements',
        );
    }
}
