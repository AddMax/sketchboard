<?php

declare(strict_types=1);

namespace App\Domain\File;

use App\Domain\File\Exception\FileNotFound;
use App\Domain\File\ValueObject\FileId;

/**
 * Порт хранилища метаданных файла. Байты сюда не попадают: за них
 * отвечает порт FileStorage в слое приложения.
 */
interface FileRepository
{
    public function nextIdentity(): FileId;

    /**
     * @throws FileNotFound
     */
    public function get(FileId $id): StoredFile;

    public function save(StoredFile $file): void;

    public function remove(StoredFile $file): void;
}
