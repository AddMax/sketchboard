<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Realtime;

use App\Application\Realtime\Query\IssueRealtimeAccess\IssueRealtimeAccessHandler;
use App\Application\Realtime\Query\IssueRealtimeAccess\IssueRealtimeAccessQuery;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Браузер получает здесь короткоживущий токен и имя канала, после чего
 * подключается к Centrifugo напрямую по WebSocket.
 */
#[OA\Tag(name: 'Realtime')]
final readonly class IssueRealtimeAccessController
{
    private const int PARTICIPANT_MAX_LENGTH = 64;

    public function __construct(private IssueRealtimeAccessHandler $issueAccess)
    {
    }

    #[Route('/api/realtime/access', name: 'realtime_access', methods: ['GET'])]
    #[OA\Get(
        summary: 'Доступ к realtime-каналу',
        description: 'JWT и имя канала для подключения к Centrifugo по `/ws`. Токен '
            .'короткоживущий и персональный, ответ отдаётся с `Cache-Control: no-store`.',
    )]
    #[OA\Parameter(
        name: 'participant',
        in: 'query',
        required: false,
        description: 'Имя участника — подпись на доске; обрезается до 64 символов, без него — anonymous',
        schema: new OA\Schema(type: 'string', maxLength: self::PARTICIPANT_MAX_LENGTH, example: 'гость-451'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Реквизиты подключения',
        content: new OA\JsonContent(ref: '#/components/schemas/RealtimeAccess'),
    )]
    public function __invoke(Request $request): JsonResponse
    {
        // Авторизации в скелете нет: имя участника приходит от клиента и
        // служит только подписью на доске. Здесь появится реальный пользователь.
        $participant = trim((string) $request->query->get('participant', ''));

        $credentials = ($this->issueAccess)(new IssueRealtimeAccessQuery(
            '' !== $participant ? mb_substr($participant, 0, self::PARTICIPANT_MAX_LENGTH) : 'anonymous',
        ));

        $response = new JsonResponse($credentials);
        // Токен короткоживущий и персональный — в кэшах ему не место
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
