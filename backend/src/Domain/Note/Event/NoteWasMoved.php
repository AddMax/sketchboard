<?php

declare(strict_types=1);

namespace App\Domain\Note\Event;

use App\Domain\Note\Note;
use App\Domain\Shared\DomainEvent;

final readonly class NoteWasMoved implements DomainEvent
{
    public function __construct(
        public Note $note,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
    }

    public function eventName(): string
    {
        return 'note.moved';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
