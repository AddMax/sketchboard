<?php

declare(strict_types=1);

namespace App\Domain\Drawing\ValueObject;

use App\Domain\Note\ValueObject\Color;
use App\Domain\Shared\InvalidArgument;

/**
 * Один штрих: ломаная из точек, цвет и толщина кисти. Идентификатор
 * назначает клиент — линия рождается в браузере и только потом попадает
 * на сервер, поэтому серверная нумерация тут ни к чему.
 */
final readonly class Line
{
    public const int MAX_POINTS = 20_000;
    public const int MAX_ID_LENGTH = 64;
    public const float MIN_WIDTH = 0.5;
    public const float MAX_WIDTH = 100.0;

    /**
     * @param non-empty-list<Point> $points
     */
    private function __construct(
        private string $id,
        private array $points,
        private Color $color,
        private float $width,
    ) {
    }

    /**
     * @param list<Point> $points
     */
    public static function create(string $id, array $points, Color $color, float $width): self
    {
        $id = trim($id);

        if ('' === $id || mb_strlen($id) > self::MAX_ID_LENGTH) {
            throw new InvalidArgument('У линии должен быть идентификатор не длиннее 64 символов', 'lines');
        }

        if ([] === $points) {
            throw new InvalidArgument('Линия должна содержать хотя бы одну точку', 'lines');
        }

        if (count($points) > self::MAX_POINTS) {
            throw new InvalidArgument(sprintf('Линия не может содержать больше %d точек', self::MAX_POINTS), 'lines');
        }

        if (!is_finite($width) || $width < self::MIN_WIDTH || $width > self::MAX_WIDTH) {
            throw new InvalidArgument(
                sprintf('Толщина линии должна быть в диапазоне %s…%s', self::MIN_WIDTH, self::MAX_WIDTH),
                'lines',
            );
        }

        return new self($id, $points, $color, $width);
    }

    /**
     * @param mixed $data ожидается {id, points: [{x, y}], color, width}
     */
    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !is_array($data['points'] ?? null)) {
            throw new InvalidArgument('Линия задаётся объектом {id, points, color, width}', 'lines');
        }

        if (!is_numeric($data['width'] ?? null)) {
            throw new InvalidArgument('Толщина линии должна быть числом', 'lines');
        }

        try {
            $color = Color::fromString(is_string($data['color'] ?? null) ? $data['color'] : '');
        } catch (InvalidArgument) {
            // Снаружи это ошибка в поле lines, а не в отдельном поле color
            throw new InvalidArgument('Цвет линии задаётся в формате #rrggbb', 'lines');
        }

        return self::create(
            id: is_scalar($data['id'] ?? null) ? (string) $data['id'] : '',
            points: array_map(Point::fromArray(...), array_values($data['points'])),
            color: $color,
            width: (float) $data['width'],
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    /**
     * @return non-empty-list<Point>
     */
    public function points(): array
    {
        return $this->points;
    }

    public function color(): Color
    {
        return $this->color;
    }

    public function width(): float
    {
        return $this->width;
    }

    /**
     * @return array{id: string, points: list<array{x: float, y: float}>, color: string, width: float}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'points' => array_map(static fn (Point $point): array => $point->toArray(), $this->points),
            'color' => $this->color->toString(),
            'width' => $this->width,
        ];
    }
}
