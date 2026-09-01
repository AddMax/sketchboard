<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Note;

use App\Domain\Note\Event\NoteWasCreated;
use App\Domain\Note\Event\NoteWasDeleted;
use App\Domain\Note\Event\NoteWasMoved;
use App\Domain\Note\Note;
use App\Domain\Note\ValueObject\Author;
use App\Domain\Note\ValueObject\Color;
use App\Domain\Note\ValueObject\NoteId;
use App\Domain\Note\ValueObject\NoteText;
use App\Domain\Note\ValueObject\Position;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Note::class)]
final class NoteTest extends TestCase
{
    public function testСозданиеЗаписываетСобытие(): void
    {
        $note = $this->note();

        $events = $note->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(NoteWasCreated::class, $events[0]);
        self::assertSame('note.created', $events[0]->eventName());
    }

    public function testСобытияОтдаютсяТолькоОдинРаз(): void
    {
        $note = $this->note();

        $note->releaseEvents();

        self::assertSame([], $note->releaseEvents());
    }

    public function testПеремещениеМеняетКоординатыИЗаписываетСобытие(): void
    {
        $note = $this->note();
        $note->releaseEvents();

        $note->moveTo(Position::at(300, 150));

        $events = $note->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NoteWasMoved::class, $events[0]);
        self::assertSame(300, $note->position()->x());
        self::assertSame(150, $note->position()->y());
    }

    public function testПеремещениеНаТоЖеМестоСобытияНеПорождает(): void
    {
        $note = $this->note();
        $note->releaseEvents();

        $note->moveTo(Position::at(10, 20));

        self::assertSame([], $note->releaseEvents(), 'Подписчиков не нужно тревожить без изменений');
    }

    public function testУдалениеЗаписываетСобытиеСИдентификатором(): void
    {
        $note = $this->note();
        $note->releaseEvents();

        $note->delete();

        $events = $note->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NoteWasDeleted::class, $events[0]);
        self::assertTrue($events[0]->noteId->equals($note->id()));
    }

    private function note(): Note
    {
        return Note::write(
            id: NoteId::generate(),
            text: NoteText::fromString('Заметка'),
            position: Position::at(10, 20),
            color: Color::default(),
            author: Author::fromString('автор'),
        );
    }
}
