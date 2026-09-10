<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Note;

use App\Application\Note\Command\MoveNote\MoveNoteCommand;
use App\Application\Note\Command\MoveNote\MoveNoteHandler;
use App\UI\Http\JsonPayload;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Заметки')]
final readonly class MoveNoteController
{
    public function __construct(private MoveNoteHandler $moveNote)
    {
    }

    #[Route('/api/notes/{id}/position', name: 'notes_move', methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Переместить заметку',
        description: 'Меняет координаты. Перемещение «на то же место» изменением не считается: '
            .'событие `note.moved` не публикуется.',
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['x', 'y'],
            properties: [
                new OA\Property(property: 'x', type: 'integer', minimum: 0, maximum: 10000, example: 300),
                new OA\Property(property: 'y', type: 'integer', minimum: 0, maximum: 10000, example: 150),
            ],
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Заметка с новыми координатами',
        content: new OA\JsonContent(ref: '#/components/schemas/Note'),
    )]
    #[OA\Response(response: 404, ref: '#/components/responses/NoteNotFound')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationError')]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $payload = JsonPayload::of($request);

        $view = ($this->moveNote)(new MoveNoteCommand(
            id: $id,
            x: $payload->int('x'),
            y: $payload->int('y'),
        ));

        return new JsonResponse($view);
    }
}
