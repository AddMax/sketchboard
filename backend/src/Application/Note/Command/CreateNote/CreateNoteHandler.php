<?php

declare(strict_types=1);

namespace App\Application\Note\Command\CreateNote;

use App\Application\Note\ReadModel\NoteView;
use App\Application\Shared\Port\DomainEventPublisher;
use App\Domain\Note\Note;
use App\Domain\Note\NoteRepository;
use App\Domain\Note\ValueObject\Author;
use App\Domain\Note\ValueObject\Color;
use App\Domain\Note\ValueObject\NoteText;
use App\Domain\Note\ValueObject\Position;

final readonly class CreateNoteHandler
{
    public function __construct(
        private NoteRepository $notes,
        private DomainEventPublisher $events,
    ) {
    }

    public function __invoke(CreateNoteCommand $command): NoteView
    {
        $note = Note::write(
            id: $this->notes->nextIdentity(),
            text: NoteText::fromString($command->text),
            position: Position::at($command->x, $command->y),
            color: null !== $command->color ? Color::fromString($command->color) : Color::default(),
            author: null !== $command->author ? Author::fromString($command->author) : Author::anonymous(),
        );

        $this->notes->save($note);

        // Публикуем только после успешной записи: подписчики не должны
        // узнать о заметке, которой в базе нет
        $this->events->publish(...$note->releaseEvents());

        return NoteView::fromNote($note);
    }
}
