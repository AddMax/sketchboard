<?php

declare(strict_types=1);

namespace App\Application\Shared\Port;

use App\Domain\Shared\DomainEvent;

/**
 * Порт публикации доменных событий за пределы процесса.
 *
 * Реализация (Redis Pub/Sub) живёт в инфраструктуре, поэтому сценарии
 * приложения не знают ни про Redis, ни про WebSocket.
 */
interface DomainEventPublisher
{
    public function publish(DomainEvent ...$events): void;
}
