<?php

declare(strict_types=1);

namespace App\Domain\File\ValueObject;

/**
 * Адрес объекта в хранилище: какой бакет (по видимости) и какой путь
 * внутри него. Путь выводится из агрегата, а не хранится в базе: домен
 * сам решает, как раскладывать файлы, и инфраструктура это не меняет.
 */
final readonly class StorageKey
{
    public function __construct(
        public Visibility $visibility,
        public string $path,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->visibility === $other->visibility && $this->path === $other->path;
    }
}
