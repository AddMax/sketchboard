<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Garage;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Тонкая обёртка над admin API Garage.
 *
 * Файлы ходят через S3 API, а здесь — только диагностика: /health отвечает
 * без токена, и этого достаточно, чтобы /api/health знал, жив ли узел.
 */
final readonly class GarageApi
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $garageAdminUrl,
    ) {
    }

    /**
     * Текст ответа /health: Garage описывает в нём состояние узла и layout.
     *
     * @throws GarageUnavailable
     */
    public function health(): string
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->garageAdminUrl, '/').'/health', [
                'timeout' => 2.0,
            ]);

            $status = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (HttpException $e) {
            throw new GarageUnavailable(sprintf('Garage недоступен: %s', $e->getMessage()), $e->getCode(), previous: $e);
        }

        // Пока узел без роли или без кворума, /health отвечает 503 с объяснением
        if (200 !== $status) {
            throw new GarageUnavailable(sprintf('Garage нездоров (HTTP %d): %s', $status, trim($body)));
        }

        return $body;
    }
}
