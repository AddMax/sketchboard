<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Application\Realtime\Query\IssueRealtimeAccess\IssueRealtimeAccessHandler;
use App\Application\Realtime\Query\IssueRealtimeAccess\IssueRealtimeAccessQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Браузер получает здесь короткоживущий токен и имя канала, после чего
 * подключается к Centrifugo напрямую по WebSocket.
 */
final readonly class RealtimeController
{
    public function __construct(private IssueRealtimeAccessHandler $issueAccess)
    {
    }

    #[Route('/api/realtime/access', name: 'realtime_access', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        // Авторизации в скелете нет: имя участника приходит от клиента и
        // служит только подписью на доске. Здесь появится реальный пользователь.
        $participant = trim((string) $request->query->get('participant', ''));

        $credentials = ($this->issueAccess)(new IssueRealtimeAccessQuery(
            '' !== $participant ? mb_substr($participant, 0, 64) : 'anonymous',
        ));

        $response = new JsonResponse($credentials);
        // Токен короткоживущий и персональный — в кэшах ему не место
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
