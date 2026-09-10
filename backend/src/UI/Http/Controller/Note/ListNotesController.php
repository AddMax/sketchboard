<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Note;

use App\Application\Note\Query\ListNotes\ListNotesHandler;
use App\Application\Note\Query\ListNotes\ListNotesQuery;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Тонкий транспортный слой: разобрать запрос, вызвать сценарий, отдать JSON.
 * Ни доменных правил, ни обращений к базе здесь нет — как и в остальных контроллерах.
 */
#[OA\Tag(name: 'Заметки')]
final readonly class ListNotesController
{
    private const int DEFAULT_LIMIT = 200;
    private const int MAX_LIMIT = 500;

    public function __construct(private ListNotesHandler $listNotes)
    {
    }

    #[Route('/api/notes', name: 'notes_list', methods: ['GET'])]
    #[OA\Get(summary: 'Список заметок', description: 'Последние заметки доски, свежие первыми.')]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        required: false,
        description: 'Сколько заметок вернуть; значения вне 1…500 приводятся к границам',
        schema: new OA\Schema(type: 'integer', minimum: 1, maximum: self::MAX_LIMIT, default: self::DEFAULT_LIMIT),
    )]
    #[OA\Response(
        response: 200,
        description: 'Заметки доски',
        content: new OA\JsonContent(
            required: ['items'],
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Note'),
                ),
            ],
        ),
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $limit = max(1, min(self::MAX_LIMIT, $request->query->getInt('limit', self::DEFAULT_LIMIT)));

        return new JsonResponse(['items' => ($this->listNotes)(new ListNotesQuery($limit))]);
    }
}
