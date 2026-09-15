<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Drawing;

use App\Domain\Drawing\Drawing;
use App\Domain\Drawing\ValueObject\Line;
use App\Domain\Drawing\ValueObject\Point;
use App\Domain\Drawing\ValueObject\Strokes;
use App\Domain\Note\ValueObject\Color;
use App\Domain\Note\ValueObject\NoteId;
use App\Domain\Shared\InvalidArgument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Drawing::class)]
#[CoversClass(Strokes::class)]
#[CoversClass(Line::class)]
#[CoversClass(Point::class)]
final class DrawingTest extends TestCase
{
    public function testNewDrawingIsEmpty(): void
    {
        $drawing = Drawing::startFor(NoteId::generate());

        self::assertCount(0, $drawing->strokes());
    }

    public function testReplacingStrokesUpdatesContentAndTime(): void
    {
        $started = new \DateTimeImmutable('2026-09-10 10:00:00');
        $drawing = Drawing::startFor(NoteId::generate(), $started);

        $drawing->replaceStrokes(Strokes::of([$this->line('a')]), new \DateTimeImmutable('2026-09-10 10:05:00'));

        self::assertCount(1, $drawing->strokes());
        self::assertSame('a', $drawing->strokes()->lines()[0]->id());
        self::assertGreaterThan($started, $drawing->updatedAt());
    }

    public function testRepeatingSameStateKeepsTime(): void
    {
        $drawing = Drawing::startFor(NoteId::generate());
        $drawing->replaceStrokes(Strokes::of([$this->line('a')]), new \DateTimeImmutable('2026-09-10 10:05:00'));
        $before = $drawing->updatedAt();

        $drawing->replaceStrokes(Strokes::of([$this->line('a')]), new \DateTimeImmutable('2026-09-10 10:09:00'));

        self::assertEquals($before, $drawing->updatedAt(), 'Ретрай клиента — не изменение');
    }

    public function testStrokesRoundTripThroughArray(): void
    {
        $raw = [
            ['id' => 'l1', 'points' => [['x' => 1.5, 'y' => -2], ['x' => 3, 'y' => 4]], 'color' => '#FF0000', 'width' => 4],
        ];

        $strokes = Strokes::fromArray($raw);

        self::assertSame(
            [['id' => 'l1', 'points' => [['x' => 1.5, 'y' => -2.0], ['x' => 3.0, 'y' => 4.0]], 'color' => '#ff0000', 'width' => 4.0]],
            $strokes->toArray(),
        );
    }

    #[DataProvider('invalidLinesProvider')]
    public function testMalformedLineIsRejected(mixed $raw): void
    {
        $this->expectException(InvalidArgument::class);

        try {
            Strokes::fromArray($raw);
        } catch (InvalidArgument $e) {
            self::assertSame('lines', $e->field());

            throw $e;
        }
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidLinesProvider(): iterable
    {
        $ok = ['id' => 'l1', 'points' => [['x' => 0, 'y' => 0]], 'color' => '#000000', 'width' => 2];

        yield 'не список' => [['id' => 'l1']];
        yield 'линия не объект' => [['строка']];
        yield 'без точек' => [[['id' => 'l1', 'points' => [], 'color' => '#000000', 'width' => 2]]];
        yield 'точка без координаты' => [[['id' => 'l1', 'points' => [['x' => 1]], 'color' => '#000000', 'width' => 2]]];
        yield 'координата не число' => [[['id' => 'l1', 'points' => [['x' => 'а', 'y' => 0]], 'color' => '#000000', 'width' => 2]]];
        yield 'пустой идентификатор' => [[[...$ok, 'id' => '']]];
        yield 'нулевая толщина' => [[[...$ok, 'width' => 0]]];
        yield 'чрезмерная толщина' => [[[...$ok, 'width' => Line::MAX_WIDTH + 1]]];
        yield 'цвет не hex' => [[[...$ok, 'color' => 'red']]];
        yield 'слишком много линий' => [array_fill(0, Strokes::MAX_LINES + 1, $ok)];
    }

    public function testInfiniteCoordinateIsRejected(): void
    {
        $this->expectException(InvalidArgument::class);

        Point::at(INF, 0);
    }

    private function line(string $id): Line
    {
        return Line::create($id, [Point::at(0, 0), Point::at(10, 10)], Color::fromString('#000000'), 2);
    }
}
