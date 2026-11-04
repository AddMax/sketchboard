<?php

declare(strict_types=1);

namespace App\Application\File\Query\GetFile;

use App\Application\File\ReadModel\FileView;
use App\Application\Shared\Port\FileStorage;
use App\Domain\File\Exception\FileAccessDenied;
use App\Domain\File\FileRepository;
use App\Domain\File\ValueObject\FileId;
use App\Domain\Note\ValueObject\Author;

/**
 * Метаданные файла. Приватный файл и описывать можно только владельцу:
 * имя и размер — тоже данные.
 */
final readonly class GetFileHandler
{
    public function __construct(
        private FileRepository $files,
        private FileStorage $storage,
    ) {
    }

    public function __invoke(GetFileQuery $query): FileView
    {
        $file = $this->files->get(FileId::fromString($query->id));
        $requester = null !== $query->requester ? Author::fromString($query->requester) : Author::anonymous();

        if (!$file->canBeReadBy($requester)) {
            throw FileAccessDenied::toFile($file->id());
        }

        return FileView::fromFile($file, $this->storage->publicUrl($file->storageKey()));
    }
}
