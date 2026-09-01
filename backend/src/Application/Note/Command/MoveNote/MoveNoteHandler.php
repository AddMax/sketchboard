<?php

declare(strict_types=1);

namespace App\Application\Note\Command\MoveNote;

use App\Application\Note\ReadModel\NoteView;
use App\Application\Shared\Port\DomainEventPublisher;
use App\Domain\Note\NoteRepository;
use App\Domain\Note\ValueObject\NoteId;
use App\Domain\Note\ValueObject\Position;

final readonly class MoveNoteHandler
{
    public function __construct(
        private NoteRepository $notes,
        private DomainEventPublisher $events,
    ) {
    }

    public function __invoke(MoveNoteCommand $command): NoteView
    {
        $note = $this->notes->get(NoteId::fromString($command->id));
        $note->moveTo(Position::at($command->x, $command->y));

        $this->notes->save($note);
        $this->events->publish(...$note->releaseEvents());

        return NoteView::fromNote($note);
    }
}
