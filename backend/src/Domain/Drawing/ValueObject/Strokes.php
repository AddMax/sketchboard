<?php

declare(strict_types=1);

namespace App\Domain\Drawing\ValueObject;

use App\Domain\Shared\InvalidArgument;

/**
 * Все штрихи рисунка целиком. Клиент присылает полный список, а не дельту:
 * так состояние на сервере всегда равно тому, что видит автор, и порядок
 * доставки запросов не важен — побеждает последний.
 */
final readonly class Strokes implements \Countable
{
    public const int MAX_LINES = 5_000;

    /**
     * @param list<Line> $lines
     */
    private function __construct(private array $lines)
    {
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @param list<Line> $lines
     */
    public static function of(array $lines): self
    {
        if (count($lines) > self::MAX_LINES) {
            throw new InvalidArgument(sprintf('Рисунок не может содержать больше %d линий', self::MAX_LINES), 'lines');
        }

        return new self($lines);
    }

    /**
     * @param mixed $data ожидается список линий
     */
    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !array_is_list($data)) {
            throw new InvalidArgument('Линии рисунка передаются списком', 'lines');
        }

        return self::of(array_map(Line::fromArray(...), $data));
    }

    /**
     * @return list<Line>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function count(): int
    {
        return count($this->lines);
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    /**
     * @return list<array{id: string, points: list<array{x: float, y: float}>, color: string, width: float}>
     */
    public function toArray(): array
    {
        return array_map(static fn (Line $line): array => $line->toArray(), $this->lines);
    }
}
