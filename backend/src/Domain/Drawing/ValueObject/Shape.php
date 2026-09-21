<?php

declare(strict_types=1);

namespace App\Domain\Drawing\ValueObject;

use App\Domain\Note\ValueObject\Color;
use App\Domain\Shared\InvalidArgument;

/**
 * Примитивная фигура: вид плюс ограничивающий прямоугольник. Хранится
 * геометрией, а не ломаной, поэтому её можно потом сдвинуть или изменить
 * размер, а эллипс остаётся эллипсом при любом масштабе.
 */
final readonly class Shape implements Element
{
    public const string TYPE = 'shape';
    public const int MAX_ID_LENGTH = Line::MAX_ID_LENGTH;
    public const float MIN_STROKE_WIDTH = Line::MIN_WIDTH;
    public const float MAX_STROKE_WIDTH = Line::MAX_WIDTH;

    private function __construct(
        private string $id,
        private ShapeKind $kind,
        private Point $origin,
        private float $width,
        private float $height,
        private Color $strokeColor,
        private float $strokeWidth,
        private ?Color $fill,
    ) {
    }

    public static function create(
        string $id,
        ShapeKind $kind,
        Point $origin,
        float $width,
        float $height,
        Color $strokeColor,
        float $strokeWidth,
        ?Color $fill = null,
    ): self {
        $id = trim($id);

        if ('' === $id || mb_strlen($id) > self::MAX_ID_LENGTH) {
            throw new InvalidArgument('У фигуры должен быть идентификатор не длиннее 64 символов', 'elements');
        }

        // Вырожденная фигура невидима и лишь засоряет рисунок; отрицательные
        // размеры клиент обязан нормализовать сам — origin всегда левый верхний угол
        if (!is_finite($width) || !is_finite($height) || $width <= 0 || $height <= 0) {
            throw new InvalidArgument('Ширина и высота фигуры должны быть положительными конечными числами', 'elements');
        }

        if (!is_finite($strokeWidth) || $strokeWidth < self::MIN_STROKE_WIDTH || $strokeWidth > self::MAX_STROKE_WIDTH) {
            throw new InvalidArgument(
                sprintf('Толщина контура должна быть в диапазоне %s…%s', self::MIN_STROKE_WIDTH, self::MAX_STROKE_WIDTH),
                'elements',
            );
        }

        return new self($id, $kind, $origin, $width, $height, $strokeColor, $strokeWidth, $fill);
    }

    /**
     * @param array<array-key, mixed> $data ожидается {id, kind, x, y, width, height, strokeColor, strokeWidth, fill?}
     */
    public static function fromArray(array $data): self
    {
        foreach (['width', 'height', 'strokeWidth'] as $key) {
            if (!is_numeric($data[$key] ?? null)) {
                throw new InvalidArgument(sprintf('Поле %s фигуры должно быть числом', $key), 'elements');
            }
        }

        $fill = $data['fill'] ?? null;

        return self::create(
            id: is_scalar($data['id'] ?? null) ? (string) $data['id'] : '',
            kind: ShapeKind::fromString(is_string($data['kind'] ?? null) ? $data['kind'] : ''),
            origin: Point::fromArray(['x' => $data['x'] ?? null, 'y' => $data['y'] ?? null]),
            width: (float) $data['width'],
            height: (float) $data['height'],
            strokeColor: self::color($data['strokeColor'] ?? null, 'Цвет контура'),
            strokeWidth: (float) $data['strokeWidth'],
            fill: null === $fill ? null : self::color($fill, 'Цвет заливки'),
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function kind(): ShapeKind
    {
        return $this->kind;
    }

    public function origin(): Point
    {
        return $this->origin;
    }

    public function width(): float
    {
        return $this->width;
    }

    public function height(): float
    {
        return $this->height;
    }

    public function strokeColor(): Color
    {
        return $this->strokeColor;
    }

    public function strokeWidth(): float
    {
        return $this->strokeWidth;
    }

    public function fill(): ?Color
    {
        return $this->fill;
    }

    /**
     * @return array{type: 'shape', id: string, kind: string, x: float, y: float, width: float, height: float, strokeColor: string, strokeWidth: float, fill: ?string}
     */
    public function toArray(): array
    {
        return [
            'type' => self::TYPE,
            'id' => $this->id,
            'kind' => $this->kind->value,
            'x' => $this->origin->x(),
            'y' => $this->origin->y(),
            'width' => $this->width,
            'height' => $this->height,
            'strokeColor' => $this->strokeColor->toString(),
            'strokeWidth' => $this->strokeWidth,
            'fill' => $this->fill?->toString(),
        ];
    }

    private static function color(mixed $raw, string $what): Color
    {
        try {
            return Color::fromString(is_string($raw) ? $raw : '');
        } catch (InvalidArgument) {
            // Снаружи это ошибка в поле elements, а не в отдельном поле цвета
            throw new InvalidArgument(sprintf('%s фигуры задаётся в формате #rrggbb', $what), 'elements');
        }
    }
}
