<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime\Centrifugo;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Тонкая обёртка над HTTP API Centrifugo.
 *
 * API серверное: ключ никогда не покидает контейнер php, браузер ходит
 * только по WebSocket.
 */
final readonly class CentrifugoApi
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $centrifugoApiUrl,
        private string $centrifugoApiKey,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws CentrifugoUnavailable
     */
    public function publish(string $channel, array $data): void
    {
        $this->call('publish', ['channel' => $channel, 'data' => $data]);
    }

    /**
     * Число участников канала прямо сейчас.
     *
     * @throws CentrifugoUnavailable
     */
    public function presenceCount(string $channel): int
    {
        $result = $this->call('presence_stats', ['channel' => $channel]);
        $count = $result['num_clients'] ?? 0;

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     *
     * @throws CentrifugoUnavailable
     */
    private function call(string $method, array $payload): array
    {
        try {
            $response = $this->httpClient->request('POST', rtrim($this->centrifugoApiUrl, '/').'/'.$method, [
                'headers' => [
                    'X-API-Key' => $this->centrifugoApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 2.0,
            ]);

            /** @var array{error?: array{message?: string, code?: int}, result?: array<string, mixed>} $body */
            $body = $response->toArray(false);
        } catch (HttpException $e) {
            throw new CentrifugoUnavailable(sprintf('Centrifugo недоступен: %s', $e->getMessage()), $e->getCode(), previous: $e);
        }

        // Centrifugo отвечает 200 даже на логические ошибки — смотрим тело
        if (isset($body['error'])) {
            throw new CentrifugoUnavailable(sprintf(
                'Centrifugo отклонил вызов %s: %s (код %d)',
                $method,
                $body['error']['message'] ?? 'без описания',
                $body['error']['code'] ?? 0,
            ));
        }

        return $body['result'] ?? [];
    }
}
