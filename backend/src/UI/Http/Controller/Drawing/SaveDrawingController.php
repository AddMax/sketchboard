<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Drawing;

use App\Application\Drawing\Command\SaveDrawing\SaveDrawingCommand;
use App\Application\Drawing\Command\SaveDrawing\SaveDrawingHandler;
use App\UI\Http\JsonPayload;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PUT, а не PATCH: клиент всегда присылает полное состояние рисунка.
 */
#[OA\Tag(name: 'Рисунок')]
final readonly class SaveDrawingController
{
    public function __construct(private SaveDrawingHandler $saveDrawing)
    {
    }

    #[Route('/api/notes/{id}/drawing', name: 'drawing_save', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Сохранить рисунок',
        description: 'Заменяет элементы рисунка — штрихи и фигуры — целиком; побеждает последнее сохранение. '
            .'Повтор того же состояния время изменения не двигает. '
            .'Realtime-событий не порождает: рисунок может весить сотни килобайт.',
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['elements'],
            properties: [
                new OA\Property(
                    property: 'elements',
                    description: 'Штрихи и фигуры в порядке наложения',
                    type: 'array',
                    maxItems: 5000,
                    items: new OA\Items(ref: '#/components/schemas/DrawingElement'),
                ),
            ],
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Сохранённый рисунок',
        content: new OA\JsonContent(ref: '#/components/schemas/Drawing'),
    )]
    #[OA\Response(response: 404, ref: '#/components/responses/NoteNotFound')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationError')]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $payload = JsonPayload::of($request);

        return new JsonResponse(($this->saveDrawing)(new SaveDrawingCommand(
            noteId: $id,
            elements: $payload->list('elements'),
        )));
    }
}
