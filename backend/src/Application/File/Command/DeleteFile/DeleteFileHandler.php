<?php

declare(strict_types=1);

namespace App\Application\File\Command\DeleteFile;

use App\Application\Shared\Port\FileStorage;
use App\Domain\File\Exception\FileAccessDenied;
use App\Domain\File\FileRepository;
use App\Domain\File\ValueObject\FileId;
use App\Domain\Note\ValueObject\Author;

final readonly class DeleteFileHandler
{
    public function __construct(
        private FileRepository $files,
        private FileStorage $storage,
    ) {
    }

    public function __invoke(DeleteFileCommand $command): void
    {
        $file = $this->files->get(FileId::fromString($command->id));
        $requester = null !== $command->requester ? Author::fromString($command->requester) : Author::anonymous();

        if (!$file->isOwnedBy($requester)) {
            throw FileAccessDenied::toDeleteFile($file->id());
        }

        // Сначала запись, потом байты: объект без записи — безобидный сирота,
        // а запись без объекта — файл, который нельзя ни прочитать, ни удалить
        $this->files->remove($file);
        $this->storage->remove($file->storageKey());
    }
}
