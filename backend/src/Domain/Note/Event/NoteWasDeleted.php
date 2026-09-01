<?php

declare(strict_types=1);

namespace App\Domain\Note\Event;

use App\Domain\Note\ValueObject\NoteId;
use App\Domain\Shared\DomainEvent;

final readonly class NoteWasDeleted implements DomainEvent
{
    public function __construct(
        public NoteId $noteId,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
    }

    public function eventName(): string
    {
        return 'note.deleted';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
