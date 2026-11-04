<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Note;

use App\Application\Note\Command\DeleteNote\DeleteNoteCommand;
use App\Application\Note\Command\DeleteNote\DeleteNoteHandler;
use App\Application\Shared\Port\DomainEventPublisher;
use App\Domain\Drawing\Drawing;
use App\Domain\Drawing\DrawingRepository;
use App\Domain\Drawing\ValueObject\Elements;
use App\Domain\File\StoredFile;
use App\Domain\File\ValueObject\FileId;
use App\Domain\File\ValueObject\FileSize;
use App\Domain\File\ValueObject\MimeType;
use App\Domain\File\ValueObject\OriginalName;
use App\Domain\File\ValueObject\Visibility;
use App\Domain\Note\Event\NoteWasDeleted;
use App\Domain\Note\Exception\NoteNotFound;
use App\Domain\Note\Note;
use App\Domain\Note\NoteRepository;
use App\Domain\Note\ValueObject\Author;
use App\Domain\Note\ValueObject\Color;
use App\Domain\Note\ValueObject\NoteId;
use App\Domain\Note\ValueObject\NoteText;
use App\Domain\Note\ValueObject\Position;
use App\Domain\Shared\DomainEvent;
use App\Tests\Unit\Application\File\InMemoryFileRepository;
use App\Tests\Unit\Application\File\InMemoryFileStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Удаление заметки тянет за собой рисунок и файлы его картинок:
 * связь между агрегатами держит сценарий, а не база.
 */
#[CoversClass(DeleteNoteHandler::class)]
final class DeleteNoteHandlerTest extends TestCase
{
    private InMemoryNotes $notes;
    private InMemoryDrawings $drawings;
    private InMemoryFileRepository $files;
    private InMemoryFileStorage $storage;
    private RecordingPublisher $events;
    private DeleteNoteHandler $handler;

    protected function setUp(): void
    {
        $this->notes = new InMemoryNotes();
        $this->drawings = new InMemoryDrawings();
        $this->files = new InMemoryFileRepository();
        $this->storage = new InMemoryFileStorage();
        $this->events = new RecordingPublisher();
        $this->handler = new DeleteNoteHandler($this->notes, $this->drawings, $this->files, $this->storage, $this->events);
    }

    public function testDeletesNoteWithDrawingAndItsImages(): void
    {
        $note = $this->note();
        $kept = $this->file();
        $used = $this->file();
        $this->drawings->save($this->drawingWith($note->id(), [$used->id()->toString(), $used->id()->toString()]));

        ($this->handler)(new DeleteNoteCommand($note->id()->toString()));

        self::assertSame(1, $this->files->count(), 'файл другой заметки остаётся');
        self::assertTrue($this->storage->has($kept->storageKey()));
        self::assertFalse($this->storage->has($used->storageKey()), 'объект картинки удалён вместе с заметкой');
        self::assertNull($this->drawings->find($note->id()));
        self::assertInstanceOf(NoteWasDeleted::class, $this->events->published[0] ?? null);
    }

    public function testDanglingAndForeignFileReferencesDoNotBlockDeletion(): void
    {
        $note = $this->note();
        $this->drawings->save($this->drawingWith($note->id(), [FileId::generate()->toString(), 'sha1-из-excalidraw']));

        ($this->handler)(new DeleteNoteCommand($note->id()->toString()));

        $this->expectException(NoteNotFound::class);
        $this->notes->get($note->id());
    }

    public function testNoteWithoutDrawingIsSimplyDeleted(): void
    {
        $note = $this->note();

        ($this->handler)(new DeleteNoteCommand($note->id()->toString()));

        self::assertCount(1, $this->events->published);
    }

    private function note(): Note
    {
        $note = Note::write(NoteId::generate(), NoteText::fromString('заметка'), Position::at(0, 0), Color::default(), Author::anonymous());
        $note->releaseEvents();
        $this->notes->save($note);

        return $note;
    }

    private function file(): StoredFile
    {
        $file = StoredFile::upload(
            FileId::generate(),
            OriginalName::fromString('img.png'),
            MimeType::fromString('image/png'),
            FileSize::ofBytes(3),
            Visibility::Public,
            Author::anonymous(),
        );
        $this->files->save($file);
        $this->storage->store($file->storageKey(), 'png', $file->mimeType());

        return $file;
    }

    /**
     * @param list<string> $fileIds
     */
    private function drawingWith(NoteId $noteId, array $fileIds): Drawing
    {
        $drawing = Drawing::startFor($noteId);
        $drawing->replaceElements(Elements::fromArray(array_map(
            static fn (string $fileId, int $i): array => ['id' => 'img'.$i, 'type' => 'image', 'fileId' => $fileId],
            $fileIds,
            array_keys($fileIds),
        )));

        return $drawing;
    }
}

final class InMemoryNotes implements NoteRepository
{
    /** @var array<string, Note> */
    private array $notes = [];

    public function nextIdentity(): NoteId
    {
        return NoteId::generate();
    }

    public function get(NoteId $id): Note
    {
        return $this->notes[$id->toString()] ?? throw NoteNotFound::withId($id);
    }

    public function save(Note $note): void
    {
        $this->notes[$note->id()->toString()] = $note;
    }

    public function remove(Note $note): void
    {
        unset($this->notes[$note->id()->toString()]);
    }

    public function latest(int $limit = 200): array
    {
        return array_slice(array_values($this->notes), 0, $limit);
    }
}

final class InMemoryDrawings implements DrawingRepository
{
    /** @var array<string, Drawing> */
    private array $drawings = [];

    public function find(NoteId $noteId): ?Drawing
    {
        return $this->drawings[$noteId->toString()] ?? null;
    }

    public function save(Drawing $drawing): void
    {
        $this->drawings[$drawing->noteId()->toString()] = $drawing;
    }

    public function removeFor(NoteId $noteId): void
    {
        unset($this->drawings[$noteId->toString()]);
    }
}

final class RecordingPublisher implements DomainEventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(DomainEvent ...$events): void
    {
        $this->published = array_merge($this->published, array_values($events));
    }
}
