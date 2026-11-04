<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Flysystem;

use App\Application\Shared\Port\FileStorage;
use App\Application\Shared\Port\StorageUnavailable;
use App\Domain\File\ValueObject\MimeType;
use App\Domain\File\ValueObject\StorageKey;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;

/**
 * Хранилище байтов на Flysystem: два файловых системы — по одной на бакет.
 * Какой бакет — решает видимость ключа, а что за адаптером (S3 Garage в
 * стеке, память в тестах) — конфиг бандла.
 */
final readonly class FlysystemFileStorage implements FileStorage
{
    public function __construct(
        private FilesystemOperator $publicStorage,
        private FilesystemOperator $privateStorage,
        private string $publicFilesBaseUrl,
    ) {
    }

    public function store(StorageKey $key, string $contents, MimeType $mimeType): void
    {
        try {
            // Content-Type задаём сами: с ним web-endpoint Garage отдаёт
            // публичный файл, а угадывать тип по расширению адаптеру незачем —
            // домен уже проверил его по содержимому
            $this->filesystem($key)->write($key->path, $contents, ['ContentType' => $mimeType->toString()]);
        } catch (FilesystemException $e) {
            throw $this->unavailable('записать', $key, $e);
        }
    }

    public function read(StorageKey $key): string
    {
        try {
            return $this->filesystem($key)->read($key->path);
        } catch (FilesystemException $e) {
            throw $this->unavailable('прочитать', $key, $e);
        }
    }

    public function remove(StorageKey $key): void
    {
        try {
            // Flysystem и S3 не считают удаление отсутствующего объекта ошибкой
            $this->filesystem($key)->delete($key->path);
        } catch (FilesystemException $e) {
            throw $this->unavailable('удалить', $key, $e);
        }
    }

    public function publicUrl(StorageKey $key): ?string
    {
        if (!$key->visibility->isPublic()) {
            return null;
        }

        return rtrim($this->publicFilesBaseUrl, '/').'/'.$key->path;
    }

    private function filesystem(StorageKey $key): FilesystemOperator
    {
        return $key->visibility->isPublic() ? $this->publicStorage : $this->privateStorage;
    }

    private function unavailable(string $action, StorageKey $key, FilesystemException $e): StorageUnavailable
    {
        return new StorageUnavailable(
            sprintf('Не удалось %s объект %s/%s: %s', $action, $key->visibility->value, $key->path, $e->getMessage()),
            previous: $e,
        );
    }
}
