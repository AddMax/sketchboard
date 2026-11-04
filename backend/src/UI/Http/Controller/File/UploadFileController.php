<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\File;

use App\Application\File\Command\UploadFile\UploadFileCommand;
use App\Application\File\Command\UploadFile\UploadFileHandler;
use App\UI\Http\UploadedFilePayload;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Файлы')]
final readonly class UploadFileController
{
    public function __construct(private UploadFileHandler $uploadFile)
    {
    }

    #[Route('/api/files', name: 'files_upload', methods: ['POST'])]
    #[OA\Post(
        summary: 'Загрузить файл',
        description: 'Файл уходит в хранилище, метаданные — в базу. Тип определяется по содержимому, '
            .'а не по расширению; принимаются картинки (PNG, JPEG, GIF, WebP, SVG), PDF и простой текст '
            .'до 10 МБ. У публичного файла в ответе есть прямая ссылка `url`, приватный читается '
            .'только владельцем через `GET /api/files/{id}/content`.',
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['file'],
                properties: [
                    new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'Содержимое файла'),
                    new OA\Property(
                        property: 'visibility',
                        type: 'string',
                        enum: ['public', 'private'],
                        default: 'public',
                        description: 'Публичный файл читает кто угодно, приватный — только владелец',
                    ),
                    new OA\Property(
                        property: 'owner',
                        type: 'string',
                        maxLength: 64,
                        example: 'гость-451',
                        description: 'Владелец; без него — anonymous',
                        nullable: true,
                    ),
                ],
            ),
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Загруженный файл',
        content: new OA\JsonContent(ref: '#/components/schemas/File'),
    )]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationError')]
    #[OA\Response(response: 503, ref: '#/components/responses/StorageUnavailable')]
    public function __invoke(Request $request): JsonResponse
    {
        $file = UploadedFilePayload::of($request);

        $view = ($this->uploadFile)(new UploadFileCommand(
            name: $file->name,
            mimeType: $file->mimeType,
            contents: $file->contents,
            visibility: $request->request->getString('visibility', 'public'),
            owner: $request->request->getString('owner') ?: null,
        ));

        return new JsonResponse($view, Response::HTTP_CREATED);
    }
}
