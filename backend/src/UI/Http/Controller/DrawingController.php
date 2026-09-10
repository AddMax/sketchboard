<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Application\Drawing\Command\SaveDrawing\SaveDrawingCommand;
use App\Application\Drawing\Command\SaveDrawing\SaveDrawingHandler;
use App\Application\Drawing\Query\GetDrawing\GetDrawingHandler;
use App\Application\Drawing\Query\GetDrawing\GetDrawingQuery;
use App\UI\Http\JsonPayload;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Рисунок заметки. PUT, а не PATCH: клиент всегда присылает полное состояние.
 */
#[Route('/api/notes/{id}/drawing')]
final readonly class DrawingController
{
    public function __construct(
        private GetDrawingHandler $getDrawing,
        private SaveDrawingHandler $saveDrawing,
    ) {
    }

    #[Route('', name: 'drawing_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return new JsonResponse(($this->getDrawing)(new GetDrawingQuery($id)));
    }

    #[Route('', name: 'drawing_save', methods: ['PUT'])]
    public function save(string $id, Request $request): JsonResponse
    {
        $payload = JsonPayload::of($request);

        return new JsonResponse(($this->saveDrawing)(new SaveDrawingCommand(
            noteId: $id,
            lines: $payload->list('lines'),
        )));
    }
}
