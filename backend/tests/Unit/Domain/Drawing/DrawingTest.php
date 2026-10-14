<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Drawing;

use App\Domain\Drawing\Drawing;
use App\Domain\Drawing\ValueObject\Element;
use App\Domain\Drawing\ValueObject\Elements;
use App\Domain\Note\ValueObject\NoteId;
use App\Domain\Shared\InvalidArgument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Drawing::class)]
#[CoversClass(Elements::class)]
#[CoversClass(Element::class)]
final class DrawingTest extends TestCase
{
    public function testNewDrawingIsEmpty(): void
    {
        $drawing = Drawing::startFor(NoteId::generate());

        self::assertCount(0, $drawing->elements());
    }

    public function testReplacingElementsUpdatesContentAndTime(): void
    {
        $started = new \DateTimeImmutable('2026-09-10 10:00:00');
        $drawing = Drawing::startFor(NoteId::generate(), $started);

        $drawing->replaceElements(Elements::of([$this->rectangle('a')]), new \DateTimeImmutable('2026-09-10 10:05:00'));

        self::assertCount(1, $drawing->elements());
        self::assertSame('a', $drawing->elements()->all()[0]->id());
        self::assertGreaterThan($started, $drawing->updatedAt());
    }

    public function testRepeatingSameStateKeepsTime(): void
    {
        $drawing = Drawing::startFor(NoteId::generate());
        $drawing->replaceElements(Elements::of([$this->rectangle('a')]), new \DateTimeImmutable('2026-09-10 10:05:00'));
        $before = $drawing->updatedAt();

        $drawing->replaceElements(Elements::of([$this->rectangle('a')]), new \DateTimeImmutable('2026-09-10 10:09:00'));

        self::assertEquals($before, $drawing->updatedAt(), 'Ретрай клиента — не изменение');
    }

    public function testElementIsStoredAsGiven(): void
    {
        // Поля редактора — version, seed, roundness, customData — домен не трогает
        $raw = [
            'id' => 'f1', 'type' => 'freedraw', 'x' => 1.5, 'y' => -2, 'width' => 10, 'height' => 4,
            'points' => [[0, 0], [10, 4]], 'pressures' => [], 'simulatePressure' => true,
            'strokeColor' => '#1e1e1e', 'strokeWidth' => 1, 'roundness' => ['type' => 2],
            'version' => 7, 'versionNonce' => 123, 'seed' => 42, 'isDeleted' => false,
            'customData' => ['note' => 'мой'],
        ];

        $elements = Elements::fromArray([$raw]);

        self::assertSame([$raw], $elements->toArray());
        self::assertSame('freedraw', $elements->all()[0]->type());
    }

    public function testElementsKeepTheirOrder(): void
    {
        $elements = Elements::fromArray([
            ['id' => 'b', 'type' => 'ellipse'],
            ['id' => 'a', 'type' => 'text', 'text' => 'привет'],
        ]);

        self::assertSame(['b', 'a'], array_map(static fn (Element $element): string => $element->id(), $elements->all()));
    }

    #[DataProvider('invalidElementsProvider')]
    public function testMalformedElementIsRejected(mixed $raw): void
    {
        $this->expectException(InvalidArgument::class);

        try {
            Elements::fromArray($raw);
        } catch (InvalidArgument $e) {
            self::assertSame('elements', $e->field());

            throw $e;
        }
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidElementsProvider(): iterable
    {
        $element = ['id' => 'e1', 'type' => 'rectangle', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10];

        yield 'не список' => [['id' => 'e1']];
        yield 'элемент не объект' => [['строка']];
        yield 'элемент — список' => [[[1, 2, 3]]];
        yield 'без идентификатора' => [[array_diff_key($element, ['id' => true])]];
        yield 'пустой идентификатор' => [[[...$element, 'id' => '  ']]];
        yield 'идентификатор не строка' => [[[...$element, 'id' => 12]]];
        yield 'чрезмерный идентификатор' => [[[...$element, 'id' => str_repeat('x', Element::MAX_ID_LENGTH + 1)]]];
        yield 'без типа' => [[array_diff_key($element, ['type' => true])]];
        yield 'тип не строка' => [[[...$element, 'type' => 1]]];
        yield 'тип с заглавными' => [[[...$element, 'type' => 'Rectangle']]];
        yield 'тип с пробелом' => [[[...$element, 'type' => 'free draw']]];
        yield 'картинка' => [[[...$element, 'type' => 'image']]];
        yield 'удалённый элемент' => [[[...$element, 'isDeleted' => true]]];
        yield 'точки не список' => [[[...$element, 'type' => 'line', 'points' => 'много']]];
        yield 'слишком много точек' => [[[...$element, 'type' => 'freedraw', 'points' => array_fill(0, Element::MAX_POINTS + 1, [0, 0])]]];
        yield 'слишком много элементов' => [array_fill(0, Elements::MAX_ELEMENTS + 1, $element)];
    }

    public function testDeletedFlagSetToFalseIsAccepted(): void
    {
        $elements = Elements::fromArray([['id' => 'e1', 'type' => 'diamond', 'isDeleted' => false]]);

        self::assertCount(1, $elements);
    }

    private function rectangle(string $id): Element
    {
        return Element::fromArray(['id' => $id, 'type' => 'rectangle', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10]);
    }
}
