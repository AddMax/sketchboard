<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Infrastructure\Realtime\Centrifugo\CentrifugoApi;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Проверка связности стека. Намеренно ходит в инфраструктуру напрямую:
 * это диагностика окружения, а не сценарий приложения.
 */
#[OA\Tag(name: 'Диагностика')]
final readonly class HealthController
{
    public function __construct(
        private Connection $connection,
        private \Redis $redis,
        private CentrifugoApi $centrifugo,
        private string $realtimeChannel,
    ) {
    }

    #[Route('/api/health', name: 'health', methods: ['GET'])]
    #[OA\Get(
        summary: 'Состояние стека',
        description: 'Проверяет PostgreSQL, Redis и Centrifugo одним запросом. '
            .'Любой сбой — `503` и текст ошибки вместо `ok` в соответствующем поле.',
    )]
    #[OA\Response(
        response: 200,
        description: 'Все зависимости отвечают',
        content: new OA\JsonContent(ref: '#/components/schemas/Health'),
    )]
    #[OA\Response(
        response: 503,
        description: 'Хотя бы одна зависимость недоступна',
        content: new OA\JsonContent(ref: '#/components/schemas/Health'),
    )]
    public function __invoke(): JsonResponse
    {
        $checks = [
            'php' => PHP_VERSION,
            'symfony' => Kernel::VERSION,
            'postgres' => $this->probe(fn (): mixed => $this->connection->executeQuery('SELECT 1')->fetchOne()),
            'redis' => $this->probe(fn (): \Redis|string|bool => $this->redis->ping()),
            'centrifugo' => $this->probe(fn (): int => $this->centrifugo->presenceCount($this->realtimeChannel)),
        ];

        $healthy = 'ok' === $checks['postgres']
            && 'ok' === $checks['redis']
            && 'ok' === $checks['centrifugo'];

        return new JsonResponse(
            ['status' => $healthy ? 'ok' : 'degraded'] + $checks,
            $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    private function probe(callable $check): string
    {
        try {
            $check();

            return 'ok';
        } catch (\Throwable $e) {
            return 'error: '.$e->getMessage();
        }
    }
}
