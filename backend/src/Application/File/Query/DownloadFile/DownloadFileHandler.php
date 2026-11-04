<?php

declare(strict_types=1);

namespace App\Application\File\Query\DownloadFile;

use App\Application\File\ReadModel\FileContent;
use App\Application\Shared\Port\FileStorage;
use App\Domain\File\Exception\FileAccessDenied;
use App\Domain\File\FileRepository;
use App\Domain\File\ValueObject\FileId;
use App\Domain\Note\ValueObject\Author;

/**
 * Содержимое файла через бэкенд. Для публичных файлов есть прямая ссылка,
 * но и этот путь для них открыт: клиенту проще ходить одним способом.
 */
final readonly class DownloadFileHandler
{
    public function __construct(
        private FileRepository $files,
        private FileStorage $storage,
    ) {
    }

    public function __invoke(DownloadFileQuery $query): FileContent
    {
        $file = $this->files->get(FileId::fromString($query->id));
        $requester = null !== $query->requester ? Author::fromString($query->requester) : Author::anonymous();

        if (!$file->canBeReadBy($requester)) {
            throw FileAccessDenied::toFile($file->id());
        }

        return new FileContent(
            name: $file->name()->toString(),
            mimeType: $file->mimeType()->toString(),
            contents: $this->storage->read($file->storageKey()),
        );
    }
}
