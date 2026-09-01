<?php

declare(strict_types=1);

namespace App\Infrastructure\WebSocket;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Websocket\Server\WebsocketClientHandler;
use Amp\Websocket\Server\WebsocketGateway;
use Amp\Websocket\WebsocketClient;
use Psr\Log\LoggerInterface;

/**
 * Обслуживает одно WebSocket-соединение.
 *
 * Сообщения от клиента ретранслируются остальным напрямую (эфемерные
 * события вроде курсора и ping), а изменения данных приходят с другой
 * стороны — из Redis, куда их публикует HTTP-часть приложения.
 */
final readonly class BoardClientHandler implements WebsocketClientHandler
{
    /** События, которые клиенту разрешено рассылать напрямую. */
    private const array RELAYABLE = ['cursor', 'ping', 'typing'];

    public function __construct(
        private WebsocketGateway $gateway,
        private LoggerInterface $logger,
    ) {
    }

    public function handleClient(WebsocketClient $client, Request $request, Response $response): void
    {
        $this->gateway->addClient($client);
        $this->logger->info('Клиент {id} подключился ({total} всего)', [
            'id' => $client->getId(),
            'total' => \count($this->gateway->getClients()),
        ]);
        $this->announcePresence();

        try {
            foreach ($client as $message) {
                $this->relay($client, $message->buffer());
            }
        } finally {
            $this->logger->info('Клиент {id} отключился', ['id' => $client->getId()]);
            $this->announcePresence();
        }
    }

    /** Рассылает всем клиентам сырое JSON-сообщение из Redis. */
    public function broadcast(string $json): void
    {
        $this->gateway->broadcastText($json);
    }

    private function relay(WebsocketClient $sender, string $raw): void
    {
        try {
            $data = json_decode($raw, true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->logger->warning('Клиент {id} прислал не-JSON', ['id' => $sender->getId()]);

            return;
        }

        $event = \is_array($data) ? (string) ($data['event'] ?? '') : '';

        if (!\in_array($event, self::RELAYABLE, true)) {
            $this->logger->debug('Событие {event} от клиента не ретранслируется', ['event' => $event]);

            return;
        }

        $message = json_encode([
            'event' => $event,
            'payload' => $data['payload'] ?? null,
            'at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        $this->gateway->broadcastText($message, [$sender->getId()]);
    }

    private function announcePresence(): void
    {
        $this->gateway->broadcastText(json_encode([
            'event' => 'presence',
            'payload' => ['clients' => \count($this->gateway->getClients())],
            'at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], \JSON_THROW_ON_ERROR));
    }
}
