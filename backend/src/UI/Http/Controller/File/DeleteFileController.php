<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\File;

use App\Application\File\Command\DeleteFile\DeleteFileCommand;
use App\Application\File\Command\DeleteFile\DeleteFileHandler;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Файлы')]
final readonly class DeleteFileController
{
    public function __construct(private DeleteFileHandler $deleteFile)
    {
    }

    #[Route('/api/files/{id}', name: 'files_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Удалить файл',
        description: 'Удаляет запись и объект в хранилище. Доступно только владельцу, даже для публичного файла.',
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(ref: '#/components/parameters/Requester')]
    #[OA\Response(response: 204, description: 'Файл удалён')]
    #[OA\Response(response: 403, ref: '#/components/responses/FileAccessDenied')]
    #[OA\Response(response: 404, ref: '#/components/responses/FileNotFound')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationError')]
    #[OA\Response(response: 503, ref: '#/components/responses/StorageUnavailable')]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        ($this->deleteFile)(new DeleteFileCommand(
            id: $id,
            requester: $request->query->getString('requester') ?: null,
        ));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
