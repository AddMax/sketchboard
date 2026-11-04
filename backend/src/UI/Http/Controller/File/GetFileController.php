<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\File;

use App\Application\File\Query\GetFile\GetFileHandler;
use App\Application\File\Query\GetFile\GetFileQuery;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Файлы')]
final readonly class GetFileController
{
    public function __construct(private GetFileHandler $getFile)
    {
    }

    #[Route('/api/files/{id}', name: 'files_get', methods: ['GET'])]
    #[OA\Get(
        summary: 'Метаданные файла',
        description: 'Описание файла без содержимого. Приватный файл видит только владелец.',
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(ref: '#/components/parameters/Requester')]
    #[OA\Response(
        response: 200,
        description: 'Описание файла',
        content: new OA\JsonContent(ref: '#/components/schemas/File'),
    )]
    #[OA\Response(response: 403, ref: '#/components/responses/FileAccessDenied')]
    #[OA\Response(response: 404, ref: '#/components/responses/FileNotFound')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationError')]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return new JsonResponse(($this->getFile)(new GetFileQuery(
            id: $id,
            requester: $request->query->getString('requester') ?: null,
        )));
    }
}
