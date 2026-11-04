<?php

declare(strict_types=1);

namespace App\Application\Note\Command\DeleteNote;

use App\Application\Shared\Port\DomainEventPublisher;
use App\Application\Shared\Port\FileStorage;
use App\Domain\Drawing\DrawingRepository;
use App\Domain\File\Exception\FileNotFound;
use App\Domain\File\FileRepository;
use App\Domain\File\ValueObject\FileId;
use App\Domain\Note\NoteRepository;
use App\Domain\Note\ValueObject\NoteId;
use App\Domain\Shared\InvalidArgument;

final readonly class DeleteNoteHandler
{
    public function __construct(
        private NoteRepository $notes,
        private DrawingRepository $drawings,
        private FileRepository $files,
        private FileStorage $storage,
        private DomainEventPublisher $events,
    ) {
    }

    public function __invoke(DeleteNoteCommand $command): void
    {
        $note = $this->notes->get(NoteId::fromString($command->id));
        $note->delete();

        // Событие снимаем с агрегата до удаления: после remove() объект
        // уже не наш
        $events = $note->releaseEvents();
        // Рисунок живёт только при заметке: связь между агрегатами
        // держит сценарий, а не внешний ключ в базе. Картинки рисунка —
        // тоже: сцена ссылается на них по fileId, и без неё они никому
        // не нужны
        $this->removeImagesOf($note->id());
        $this->drawings->removeFor($note->id());
        $this->notes->remove($note);

        $this->events->publish(...$events);
    }

    private function removeImagesOf(NoteId $noteId): void
    {
        $drawing = $this->drawings->find($noteId);

        if (null === $drawing) {
            return;
        }

        foreach ($drawing->elements()->fileIds() as $fileId) {
            try {
                $file = $this->files->get(FileId::fromString($fileId));
            } catch (InvalidArgument|FileNotFound) {
                // Ссылка не на наш файл или файл уже удалён — сцене это
                // не мешало, не помешает и удалению
                continue;
            }

            // Как в DeleteFileHandler: сначала запись, потом объект
            $this->files->remove($file);
            $this->storage->remove($file->storageKey());
        }
    }
}
