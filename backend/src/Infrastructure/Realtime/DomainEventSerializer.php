<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

use App\Application\Note\ReadModel\NoteView;
use App\Domain\Note\Event\NoteWasCreated;
use App\Domain\Note\Event\NoteWasDeleted;
use App\Domain\Note\Event\NoteWasMoved;
use App\Domain\Shared\DomainEvent;

/**
 * Превращает доменное событие в сообщение для клиентов доски.
 *
 * Форма сообщения — контракт с фронтендом, то есть деталь транспорта,
 * поэтому она описана здесь, а не в самих событиях.
 */
final readonly class DomainEventSerializer
{
    public function toJson(DomainEvent $event): string
    {
        return json_encode([
            'event' => $event->eventName(),
            'payload' => $this->payload($event),
            'at' => $event->occurredAt()->format(\DateTimeInterface::ATOM),
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(DomainEvent $event): array
    {
        return match (true) {
            $event instanceof NoteWasCreated,
            $event instanceof NoteWasMoved => NoteView::fromNote($event->note)->jsonSerialize(),
            $event instanceof NoteWasDeleted => ['id' => $event->noteId->toString()],
            default => throw new \LogicException(sprintf(
                'Для события %s не описано представление',
                $event::class,
            )),
        };
    }
}
