<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Note;

use App\Application\Note\Command\DeleteNote\DeleteNoteCommand;
use App\Application\Note\Command\DeleteNote\DeleteNoteHandler;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Заметки')]
final readonly class DeleteNoteController
{
    public function __construct(private DeleteNoteHandler $deleteNote)
    {
    }

    #[Route('/api/notes/{id}', name: 'notes_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Удалить заметку',
        description: 'Вместе с заметкой удаляется её рисунок. Участники получают событие `note.deleted`.',
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 204, description: 'Заметка удалена')]
    #[OA\Response(response: 404, ref: '#/components/responses/NoteNotFound')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationError')]
    public function __invoke(string $id): JsonResponse
    {
        ($this->deleteNote)(new DeleteNoteCommand($id));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
