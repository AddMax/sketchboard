<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Note\ValueObject;

use App\Domain\Note\ValueObject\Author;
use App\Domain\Note\ValueObject\Color;
use App\Domain\Note\ValueObject\NoteId;
use App\Domain\Note\ValueObject\NoteText;
use App\Domain\Note\ValueObject\Position;
use App\Domain\Shared\InvalidArgument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoteText::class)]
#[CoversClass(Position::class)]
#[CoversClass(Color::class)]
#[CoversClass(Author::class)]
#[CoversClass(NoteId::class)]
final class ValueObjectTest extends TestCase
{
    public function testТекстОбрезаетсяПоКраям(): void
    {
        self::assertSame('текст', NoteText::fromString("  текст \n")->toString());
    }

    #[DataProvider('недопустимыйТекст')]
    public function testПустойИлиСлишкомДлинныйТекстНеПринимается(string $value, string $ожидаемоеПоле): void
    {
        $this->expectException(InvalidArgument::class);

        try {
            NoteText::fromString($value);
        } catch (InvalidArgument $e) {
            self::assertSame($ожидаемоеПоле, $e->field());

            throw $e;
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function недопустимыйТекст(): iterable
    {
        yield 'пустая строка' => ['', 'text'];
        yield 'только пробелы' => ["   \t\n", 'text'];
        yield 'длиннее предела' => [str_repeat('я', NoteText::MAX_LENGTH + 1), 'text'];
    }

    public function testЦветПриводитсяКНижнемуРегистру(): void
    {
        self::assertSame('#06d6a0', Color::fromString('#06D6A0')->toString());
    }

    #[DataProvider('недопустимыйЦвет')]
    public function testНекорректныйЦветНеПринимается(string $value): void
    {
        $this->expectException(InvalidArgument::class);

        Color::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function недопустимыйЦвет(): iterable
    {
        yield 'без решётки' => ['06d6a0'];
        yield 'сокращённая запись' => ['#fff'];
        yield 'название цвета' => ['красный'];
    }

    #[DataProvider('координатыВнеДоски')]
    public function testКоординатыЗаПределамиДоскиНеПринимаются(int $x, int $y, string $поле): void
    {
        $this->expectException(InvalidArgument::class);

        try {
            Position::at($x, $y);
        } catch (InvalidArgument $e) {
            self::assertSame($поле, $e->field());

            throw $e;
        }
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function координатыВнеДоски(): iterable
    {
        yield 'отрицательный x' => [-1, 0, 'x'];
        yield 'слишком большой y' => [0, Position::MAX + 1, 'y'];
    }

    public function testОдинаковыеКоординатыРавны(): void
    {
        self::assertTrue(Position::at(5, 7)->equals(Position::at(5, 7)));
        self::assertFalse(Position::at(5, 7)->equals(Position::at(7, 5)));
    }

    public function testАвторПоУмолчаниюАнонимен(): void
    {
        self::assertSame('anonymous', Author::anonymous()->toString());
    }

    public function testИдентификаторПроверяетФормат(): void
    {
        $this->expectException(InvalidArgument::class);

        NoteId::fromString('не-uuid');
    }

    public function testИдентификаторВосстанавливаетсяИзСтроки(): void
    {
        $id = NoteId::generate();

        self::assertTrue($id->equals(NoteId::fromString($id->toString())));
    }
}
