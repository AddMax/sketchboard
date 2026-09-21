<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Drawing;

use App\Domain\Drawing\Drawing;
use App\Domain\Drawing\ValueObject\Element;
use App\Domain\Drawing\ValueObject\Elements;
use App\Domain\Drawing\ValueObject\Line;
use App\Domain\Drawing\ValueObject\Point;
use App\Domain\Drawing\ValueObject\Shape;
use App\Domain\Drawing\ValueObject\ShapeKind;
use App\Domain\Note\ValueObject\Color;
use App\Domain\Note\ValueObject\NoteId;
use App\Domain\Shared\InvalidArgument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Drawing::class)]
#[CoversClass(Elements::class)]
#[CoversClass(Line::class)]
#[CoversClass(Shape::class)]
#[CoversClass(ShapeKind::class)]
#[CoversClass(Point::class)]
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

        $drawing->replaceElements(Elements::of([$this->line('a')]), new \DateTimeImmutable('2026-09-10 10:05:00'));

        self::assertCount(1, $drawing->elements());
        self::assertSame('a', $drawing->elements()->all()[0]->id());
        self::assertGreaterThan($started, $drawing->updatedAt());
    }

    public function testRepeatingSameStateKeepsTime(): void
    {
        $drawing = Drawing::startFor(NoteId::generate());
        $drawing->replaceElements(Elements::of([$this->line('a')]), new \DateTimeImmutable('2026-09-10 10:05:00'));
        $before = $drawing->updatedAt();

        $drawing->replaceElements(Elements::of([$this->line('a')]), new \DateTimeImmutable('2026-09-10 10:09:00'));

        self::assertEquals($before, $drawing->updatedAt(), 'Ретрай клиента — не изменение');
    }

    public function testLineRoundTripsThroughArray(): void
    {
        $raw = [
            ['type' => 'line', 'id' => 'l1', 'points' => [['x' => 1.5, 'y' => -2], ['x' => 3, 'y' => 4]], 'color' => '#FF0000', 'width' => 4],
        ];

        $elements = Elements::fromArray($raw);

        self::assertSame(
            [['type' => 'line', 'id' => 'l1', 'points' => [['x' => 1.5, 'y' => -2.0], ['x' => 3.0, 'y' => 4.0]], 'color' => '#ff0000', 'width' => 4.0]],
            $elements->toArray(),
        );
    }

    public function testShapeRoundTripsThroughArray(): void
    {
        $raw = [
            ['type' => 'shape', 'id' => 's1', 'kind' => 'ellipse', 'x' => 10, 'y' => -5.5, 'width' => 40, 'height' => 20, 'strokeColor' => '#1E88E5', 'strokeWidth' => 2, 'fill' => null],
        ];

        $elements = Elements::fromArray($raw);

        self::assertSame(
            [['type' => 'shape', 'id' => 's1', 'kind' => 'ellipse', 'x' => 10.0, 'y' => -5.5, 'width' => 40.0, 'height' => 20.0, 'strokeColor' => '#1e88e5', 'strokeWidth' => 2.0, 'fill' => null]],
            $elements->toArray(),
        );
    }

    public function testShapeKeepsFillWhenGiven(): void
    {
        $shape = Shape::fromArray(['id' => 's1', 'kind' => 'rect', 'x' => 0, 'y' => 0, 'width' => 1, 'height' => 1, 'strokeColor' => '#000000', 'strokeWidth' => 1, 'fill' => '#FFD166']);

        self::assertSame('#ffd166', $shape->fill()?->toString());
        self::assertSame(ShapeKind::Rect, $shape->kind());
    }

    public function testRecordsWithoutTypeAreLines(): void
    {
        // Так рисунки сохранялись до появления фигур
        $elements = Elements::fromArray([
            ['id' => 'l1', 'points' => [['x' => 0, 'y' => 0]], 'color' => '#000000', 'width' => 2],
        ]);

        self::assertInstanceOf(Line::class, $elements->all()[0]);
        self::assertSame('line', $elements->toArray()[0]['type']);
    }

    public function testLinesAndShapesKeepTheirOrder(): void
    {
        $elements = Elements::fromArray([
            ['type' => 'shape', 'id' => 's1', 'kind' => 'rect', 'x' => 0, 'y' => 0, 'width' => 1, 'height' => 1, 'strokeColor' => '#000000', 'strokeWidth' => 1],
            ['type' => 'line', 'id' => 'l1', 'points' => [['x' => 0, 'y' => 0]], 'color' => '#000000', 'width' => 2],
        ]);

        self::assertSame(['s1', 'l1'], array_map(static fn (Element $element): string => $element->id(), $elements->all()));
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
        $line = ['type' => 'line', 'id' => 'l1', 'points' => [['x' => 0, 'y' => 0]], 'color' => '#000000', 'width' => 2];
        $shape = ['type' => 'shape', 'id' => 's1', 'kind' => 'rect', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10, 'strokeColor' => '#000000', 'strokeWidth' => 2];

        yield 'не список' => [['id' => 'l1']];
        yield 'элемент не объект' => [['строка']];
        yield 'неизвестный тип' => [[[...$line, 'type' => 'text']]];
        yield 'линия без точек' => [[[...$line, 'points' => []]]];
        yield 'точка без координаты' => [[[...$line, 'points' => [['x' => 1]]]]];
        yield 'координата не число' => [[[...$line, 'points' => [['x' => 'а', 'y' => 0]]]]];
        yield 'пустой идентификатор линии' => [[[...$line, 'id' => '']]];
        yield 'нулевая толщина линии' => [[[...$line, 'width' => 0]]];
        yield 'чрезмерная толщина линии' => [[[...$line, 'width' => Line::MAX_WIDTH + 1]]];
        yield 'цвет линии не hex' => [[[...$line, 'color' => 'red']]];
        yield 'неизвестный вид фигуры' => [[[...$shape, 'kind' => 'star']]];
        yield 'фигура без вида' => [[array_diff_key($shape, ['kind' => true])]];
        yield 'нулевая ширина фигуры' => [[[...$shape, 'width' => 0]]];
        yield 'отрицательная высота фигуры' => [[[...$shape, 'height' => -3]]];
        yield 'ширина фигуры не число' => [[[...$shape, 'width' => 'много']]];
        yield 'координата фигуры не число' => [[[...$shape, 'x' => null]]];
        yield 'пустой идентификатор фигуры' => [[[...$shape, 'id' => '']]];
        yield 'чрезмерная толщина контура' => [[[...$shape, 'strokeWidth' => Shape::MAX_STROKE_WIDTH + 1]]];
        yield 'цвет контура не hex' => [[[...$shape, 'strokeColor' => 'blue']]];
        yield 'заливка не hex' => [[[...$shape, 'fill' => 'yellow']]];
        yield 'слишком много элементов' => [array_fill(0, Elements::MAX_ELEMENTS + 1, $line)];
    }

    public function testInfiniteCoordinateIsRejected(): void
    {
        $this->expectException(InvalidArgument::class);

        Point::at(INF, 0);
    }

    public function testInfiniteShapeSizeIsRejected(): void
    {
        $this->expectException(InvalidArgument::class);

        Shape::create('s1', ShapeKind::Rect, Point::at(0, 0), INF, 1, Color::fromString('#000000'), 1);
    }

    private function line(string $id): Line
    {
        return Line::create($id, [Point::at(0, 0), Point::at(10, 10)], Color::fromString('#000000'), 2);
    }
}
