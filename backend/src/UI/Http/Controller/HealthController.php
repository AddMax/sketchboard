<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Infrastructure\Realtime\Centrifugo\CentrifugoApi;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Проверка связности стека. Намеренно ходит в инфраструктуру напрямую:
 * это диагностика окружения, а не сценарий приложения.
 */
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
    public function __invoke(): JsonResponse
    {
        $checks = [
            'php' => \PHP_VERSION,
            'symfony' => Kernel::VERSION,
            'postgres' => $this->probe(fn () => $this->connection->executeQuery('SELECT 1')->fetchOne()),
            'redis' => $this->probe(fn () => $this->redis->ping()),
            'centrifugo' => $this->probe(fn () => $this->centrifugo->presenceCount($this->realtimeChannel)),
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
