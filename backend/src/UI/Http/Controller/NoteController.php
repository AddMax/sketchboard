<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Application\Note\Command\CreateNote\CreateNoteCommand;
use App\Application\Note\Command\CreateNote\CreateNoteHandler;
use App\Application\Note\Command\DeleteNote\DeleteNoteCommand;
use App\Application\Note\Command\DeleteNote\DeleteNoteHandler;
use App\Application\Note\Command\MoveNote\MoveNoteCommand;
use App\Application\Note\Command\MoveNote\MoveNoteHandler;
use App\Application\Note\Query\ListNotes\ListNotesHandler;
use App\Application\Note\Query\ListNotes\ListNotesQuery;
use App\UI\Http\JsonPayload;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Тонкий транспортный слой: разобрать запрос, вызвать сценарий, отдать JSON.
 * Ни доменных правил, ни обращений к базе здесь нет.
 */
#[Route('/api/notes')]
final readonly class NoteController
{
    public function __construct(
        private ListNotesHandler $listNotes,
        private CreateNoteHandler $createNote,
        private MoveNoteHandler $moveNote,
        private DeleteNoteHandler $deleteNote,
    ) {
    }

    #[Route('', name: 'notes_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $limit = max(1, min(500, $request->query->getInt('limit', 200)));

        return new JsonResponse(['items' => ($this->listNotes)(new ListNotesQuery($limit))]);
    }

    #[Route('', name: 'notes_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = JsonPayload::of($request);

        $view = ($this->createNote)(new CreateNoteCommand(
            text: $payload->string('text'),
            x: $payload->int('x'),
            y: $payload->int('y'),
            color: $payload->nullableString('color'),
            author: $payload->nullableString('author'),
        ));

        return new JsonResponse($view, Response::HTTP_CREATED);
    }

    #[Route('/{id}/position', name: 'notes_move', methods: ['PATCH'])]
    public function move(string $id, Request $request): JsonResponse
    {
        $payload = JsonPayload::of($request);

        $view = ($this->moveNote)(new MoveNoteCommand(
            id: $id,
            x: $payload->int('x'),
            y: $payload->int('y'),
        ));

        return new JsonResponse($view);
    }

    #[Route('/{id}', name: 'notes_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        ($this->deleteNote)(new DeleteNoteCommand($id));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
