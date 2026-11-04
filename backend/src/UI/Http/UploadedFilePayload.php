<?php

declare(strict_types=1);

namespace App\UI\Http;

use App\Domain\Shared\InvalidArgument;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * Файл из multipart-запроса в виде, понятном команде приложения: имя,
 * тип и байты. Единственная проверка на этом уровне — что файл вообще
 * дошёл: всё остальное (тип, размер, имя) решает домен.
 */
final readonly class UploadedFilePayload
{
    private function __construct(
        public string $name,
        public string $mimeType,
        public string $contents,
    ) {
    }

    /**
     * @throws InvalidArgument если файла нет или PHP его отбросил
     */
    public static function of(Request $request, string $field = 'file'): self
    {
        $file = $request->files->get($field);

        if (!$file instanceof UploadedFile) {
            throw new InvalidArgument('Файл не передан', $field);
        }

        if (!$file->isValid()) {
            // Сюда попадает и превышение upload_max_filesize: такой файл PHP
            // отбрасывает ещё до нас, домену проверять нечего
            throw new InvalidArgument(match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл больше допустимого размера',
                UPLOAD_ERR_PARTIAL => 'Файл загружен не полностью',
                UPLOAD_ERR_NO_FILE => 'Файл не передан',
                default => 'Файл не удалось принять',
            }, $field);
        }

        return new self(
            name: $file->getClientOriginalName(),
            // Тип — по содержимому (fileinfo), а не по заявке клиента: браузер
            // ставит его по расширению, а домен по типу решает, принимать ли файл
            mimeType: $file->getMimeType() ?? $file->getClientMimeType(),
            contents: $file->getContent(),
        );
    }
}
