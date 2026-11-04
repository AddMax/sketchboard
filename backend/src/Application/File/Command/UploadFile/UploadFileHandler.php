<?php

declare(strict_types=1);

namespace App\Application\File\Command\UploadFile;

use App\Application\File\ReadModel\FileView;
use App\Application\Shared\Port\FileStorage;
use App\Domain\File\FileRepository;
use App\Domain\File\StoredFile;
use App\Domain\File\ValueObject\FileSize;
use App\Domain\File\ValueObject\MimeType;
use App\Domain\File\ValueObject\OriginalName;
use App\Domain\File\ValueObject\Visibility;
use App\Domain\Note\ValueObject\Author;

final readonly class UploadFileHandler
{
    public function __construct(
        private FileRepository $files,
        private FileStorage $storage,
    ) {
    }

    public function __invoke(UploadFileCommand $command): FileView
    {
        $file = StoredFile::upload(
            id: $this->files->nextIdentity(),
            name: OriginalName::fromString($command->name),
            mimeType: MimeType::fromString($command->mimeType),
            size: FileSize::ofBytes(strlen($command->contents)),
            visibility: Visibility::fromString($command->visibility),
            owner: null !== $command->owner ? Author::fromString($command->owner) : Author::anonymous(),
        );

        // Сначала байты, потом запись: ссылка на несуществующий объект хуже,
        // чем объект без ссылки. Если запись не удалась — объект убираем,
        // чтобы не копить сирот
        $this->storage->store($file->storageKey(), $command->contents, $file->mimeType());

        try {
            $this->files->save($file);
        } catch (\Throwable $e) {
            $this->storage->remove($file->storageKey());

            throw $e;
        }

        return FileView::fromFile($file, $this->storage->publicUrl($file->storageKey()));
    }
}
