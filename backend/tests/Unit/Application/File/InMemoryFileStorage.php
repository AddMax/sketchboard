<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\File;

use App\Application\Shared\Port\FileStorage;
use App\Application\Shared\Port\StorageUnavailable;
use App\Domain\File\ValueObject\MimeType;
use App\Domain\File\ValueObject\StorageKey;

/**
 * Хранилище байтов в памяти. Бакеты различаются по видимости ключа,
 * как и в настоящем S3; публичная ссылка — просто путь с префиксом.
 */
final class InMemoryFileStorage implements FileStorage
{
    /** @var array<string, string> «бакет/путь» → содержимое */
    private array $objects = [];

    public function store(StorageKey $key, string $contents, MimeType $mimeType): void
    {
        $this->objects[$this->address($key)] = $contents;
    }

    public function read(StorageKey $key): string
    {
        return $this->objects[$this->address($key)]
            ?? throw new StorageUnavailable(sprintf('объекта %s нет', $this->address($key)));
    }

    public function remove(StorageKey $key): void
    {
        unset($this->objects[$this->address($key)]);
    }

    public function publicUrl(StorageKey $key): ?string
    {
        return $key->visibility->isPublic() ? '/files/'.$key->path : null;
    }

    public function has(StorageKey $key): bool
    {
        return isset($this->objects[$this->address($key)]);
    }

    public function count(): int
    {
        return count($this->objects);
    }

    private function address(StorageKey $key): string
    {
        return $key->visibility->value.'/'.$key->path;
    }
}
