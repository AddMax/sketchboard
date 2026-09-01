<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime\Centrifugo;

use App\Application\Shared\Port\DomainEventPublisher;
use App\Domain\Shared\DomainEvent;
use App\Infrastructure\Realtime\DomainEventSerializer;
use Psr\Log\LoggerInterface;

/**
 * Адаптер порта публикации: доменные события уходят в канал Centrifugo,
 * а тот доставляет их подписанным браузерам.
 *
 * php-fpm живёт один запрос и держать соединения не может — этим и занят
 * отдельный сервер.
 */
final readonly class CentrifugoEventPublisher implements DomainEventPublisher
{
    public function __construct(
        private CentrifugoApi $centrifugo,
        private DomainEventSerializer $serializer,
        private string $realtimeChannel,
        private LoggerInterface $realtimeLogger,
    ) {
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->publishOne($event);
        }
    }

    private function publishOne(DomainEvent $event): void
    {
        try {
            $this->centrifugo->publish($this->realtimeChannel, $this->serializer->toArray($event));
        } catch (CentrifugoUnavailable $e) {
            // Данные уже записаны: недоступность канала не должна ронять
            // запрос — клиенты увидят изменения при следующей загрузке
            $this->realtimeLogger->error('Не удалось опубликовать {event}: {error}', [
                'event' => $event->eventName(),
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $this->realtimeLogger->debug('Опубликовано {event} в канал {channel}', [
            'event' => $event->eventName(),
            'channel' => $this->realtimeChannel,
        ]);
    }
}
