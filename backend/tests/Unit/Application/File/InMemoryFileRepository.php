<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\File;

use App\Domain\File\Exception\FileNotFound;
use App\Domain\File\FileRepository;
use App\Domain\File\StoredFile;
use App\Domain\File\ValueObject\FileId;

/**
 * Репозиторий в памяти: сценарии проверяются без базы.
 * Опционально падает на save — чтобы проверить откат в хранилище.
 */
final class InMemoryFileRepository implements FileRepository
{
    /** @var array<string, StoredFile> */
    private array $files = [];

    public function __construct(private readonly bool $failOnSave = false)
    {
    }

    public function nextIdentity(): FileId
    {
        return FileId::generate();
    }

    public function get(FileId $id): StoredFile
    {
        return $this->files[$id->toString()] ?? throw FileNotFound::withId($id);
    }

    public function save(StoredFile $file): void
    {
        if ($this->failOnSave) {
            throw new \RuntimeException('база недоступна');
        }

        $this->files[$file->id()->toString()] = $file;
    }

    public function remove(StoredFile $file): void
    {
        unset($this->files[$file->id()->toString()]);
    }

    public function count(): int
    {
        return count($this->files);
    }
}
