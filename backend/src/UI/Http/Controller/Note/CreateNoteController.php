<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Note;

use App\Application\Note\Command\CreateNote\CreateNoteCommand;
use App\Application\Note\Command\CreateNote\CreateNoteHandler;
use App\UI\Http\JsonPayload;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Заметки')]
final readonly class CreateNoteController
{
    public function __construct(private CreateNoteHandler $createNote)
    {
    }

    #[Route('/api/notes', name: 'notes_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Создать заметку',
        description: 'Заметка сохраняется и уходит всем участникам событием `note.created`. '
            .'Идентификатор и время создания назначает сервер.',
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['text', 'x', 'y'],
            properties: [
                new OA\Property(property: 'text', type: 'string', maxLength: 2000, example: 'Купить молоко'),
                new OA\Property(property: 'x', type: 'integer', minimum: 0, maximum: 10000, example: 120),
                new OA\Property(property: 'y', type: 'integer', minimum: 0, maximum: 10000, example: 80),
                new OA\Property(
                    property: 'color',
                    type: 'string',
                    pattern: '^#[0-9a-f]{6}$',
                    example: '#ffd166',
                    description: 'Цвет стикера; без него — цвет по умолчанию',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'author',
                    type: 'string',
                    maxLength: 64,
                    example: 'гость-451',
                    description: 'Подпись автора; без неё — anonymous',
                    nullable: true,
                ),
            ],
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Созданная заметка',
        content: new OA\JsonContent(ref: '#/components/schemas/Note'),
    )]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationError')]
    public function __invoke(Request $request): JsonResponse
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
}
