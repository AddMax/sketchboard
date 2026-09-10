<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Drawing;

use App\Application\Drawing\Query\GetDrawing\GetDrawingHandler;
use App\Application\Drawing\Query\GetDrawing\GetDrawingQuery;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Рисунок')]
final readonly class GetDrawingController
{
    public function __construct(private GetDrawingHandler $getDrawing)
    {
    }

    #[Route('/api/notes/{id}/drawing', name: 'drawing_get', methods: ['GET'])]
    #[OA\Get(
        summary: 'Рисунок заметки',
        description: 'Все штрихи целиком. Если на заметке ещё не рисовали — пустой список '
            .'и `updatedAt: null`; клиенту не нужно различать «нет» и «пусто».',
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(
        response: 200,
        description: 'Штрихи рисунка',
        content: new OA\JsonContent(ref: '#/components/schemas/Drawing'),
    )]
    #[OA\Response(response: 404, ref: '#/components/responses/NoteNotFound')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationError')]
    public function __invoke(string $id): JsonResponse
    {
        return new JsonResponse(($this->getDrawing)(new GetDrawingQuery($id)));
    }
}
