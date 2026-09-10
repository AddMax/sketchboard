<?php

declare(strict_types=1);

namespace App\Application\Note\Command\DeleteNote;

use App\Application\Shared\Port\DomainEventPublisher;
use App\Domain\Drawing\DrawingRepository;
use App\Domain\Note\NoteRepository;
use App\Domain\Note\ValueObject\NoteId;

final readonly class DeleteNoteHandler
{
    public function __construct(
        private NoteRepository $notes,
        private DrawingRepository $drawings,
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
        // держит сценарий, а не внешний ключ в базе
        $this->drawings->removeFor($note->id());
        $this->notes->remove($note);

        $this->events->publish(...$events);
    }
}
