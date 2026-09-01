<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

use App\Application\Shared\Port\DomainEventPublisher;
use App\Domain\Shared\DomainEvent;
use Psr\Log\LoggerInterface;

/**
 * Адаптер порта публикации: события уходят в канал Redis Pub/Sub, откуда
 * их забирает WebSocket-сервер.
 *
 * php-fpm живёт один запрос и держать соединения с браузерами не может —
 * отсюда и разделение на два процесса через шину.
 */
final readonly class RedisDomainEventPublisher implements DomainEventPublisher
{
    public function __construct(
        private \Redis $redis,
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
            $receivers = (int) $this->redis->publish(
                $this->realtimeChannel,
                $this->serializer->toJson($event),
            );
        } catch (\RedisException $e) {
            // Падение шины не откатывает уже сохранённые данные: клиенты
            // увидят их при следующей загрузке доски
            $this->realtimeLogger->error('Не удалось опубликовать {event}: {error}', [
                'event' => $event->eventName(),
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $this->realtimeLogger->debug('Опубликовано {event} для {receivers} подписчиков', [
            'event' => $event->eventName(),
            'receivers' => $receivers,
        ]);
    }
}
