<?php

declare(strict_types=1);

namespace App\Domain\Drawing\ValueObject;

use App\Domain\Shared\InvalidArgument;

/**
 * Все элементы сцены целиком, в порядке наложения. Клиент присылает полный
 * список, а не дельту: так состояние на сервере всегда равно тому, что
 * видит автор, и порядок доставки запросов не важен — побеждает последний.
 */
final readonly class Elements implements \Countable
{
    public const int MAX_ELEMENTS = 5_000;

    /**
     * @param list<Element> $elements
     */
    private function __construct(private array $elements)
    {
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @param list<Element> $elements
     */
    public static function of(array $elements): self
    {
        if (count($elements) > self::MAX_ELEMENTS) {
            throw new InvalidArgument(sprintf('Рисунок не может содержать больше %d элементов', self::MAX_ELEMENTS), 'elements');
        }

        return new self($elements);
    }

    /**
     * @param mixed $data ожидается список элементов Excalidraw
     */
    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !array_is_list($data)) {
            throw new InvalidArgument('Элементы рисунка передаются списком', 'elements');
        }

        return self::of(array_map(Element::fromArray(...), $data));
    }

    /**
     * @return list<Element>
     */
    public function all(): array
    {
        return $this->elements;
    }

    public function count(): int
    {
        return count($this->elements);
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn (Element $element): array => $element->toArray(), $this->elements);
    }
}
