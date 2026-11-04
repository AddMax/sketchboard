<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\File;

use App\Application\File\Query\DownloadFile\DownloadFileHandler;
use App\Application\File\Query\DownloadFile\DownloadFileQuery;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Файлы')]
final readonly class DownloadFileController
{
    public function __construct(private DownloadFileHandler $downloadFile)
    {
    }

    #[Route('/api/files/{id}/content', name: 'files_download', methods: ['GET'])]
    #[OA\Get(
        summary: 'Содержимое файла',
        description: 'Байты файла с его типом и именем. Единственный путь к приватному файлу: '
            .'публичный доступен и напрямую по `url` из описания, минуя бэкенд.',
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(ref: '#/components/parameters/Requester')]
    #[OA\Response(
        response: 200,
        description: 'Содержимое файла; тип — в Content-Type, имя — в Content-Disposition',
        content: new OA\MediaType(mediaType: '*/*', schema: new OA\Schema(type: 'string', format: 'binary')),
    )]
    #[OA\Response(response: 403, ref: '#/components/responses/FileAccessDenied')]
    #[OA\Response(response: 404, ref: '#/components/responses/FileNotFound')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationError')]
    #[OA\Response(response: 503, ref: '#/components/responses/StorageUnavailable')]
    public function __invoke(string $id, Request $request): Response
    {
        $file = ($this->downloadFile)(new DownloadFileQuery(
            id: $id,
            requester: $request->query->getString('requester') ?: null,
        ));

        $response = new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => $file->mimeType,
            'Content-Length' => (string) strlen($file->contents),
            // Файл пришёл от пользователя: SVG или PDF может нести скрипт,
            // и, открытый по прямой ссылке, он выполнился бы в origin приложения.
            // Песочница это запрещает, а <img> и <embed> она не мешает
            'Content-Security-Policy' => 'sandbox',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        // Имя может быть любым Unicode; заголовку нужен ещё и ASCII-вариант
        // для старых клиентов — транслитерация читается лучше подчёркиваний
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $file->name,
            $this->asciiName($file->name),
        ));

        return $response;
    }

    /**
     * Запасное имя: только печатные ASCII без «%», «/» и «\\» — таковы
     * требования Content-Disposition к filename без кодировки.
     */
    private function asciiName(string $name): string
    {
        $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII', $name);
        $ascii = preg_replace('/[^\x20-\x7e]|[%\/\\\\]/', '_', false === $ascii ? $name : $ascii) ?? '';

        return '' === trim($ascii, '_. ') ? 'file' : $ascii;
    }
}
