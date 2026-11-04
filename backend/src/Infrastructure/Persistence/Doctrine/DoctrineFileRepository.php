<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\File\Exception\FileNotFound;
use App\Domain\File\FileRepository;
use App\Domain\File\StoredFile;
use App\Domain\File\ValueObject\FileId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineFileRepository implements FileRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function nextIdentity(): FileId
    {
        return FileId::generate();
    }

    public function get(FileId $id): StoredFile
    {
        $file = $this->entityManager->find(StoredFile::class, $id);

        if (null === $file) {
            throw FileNotFound::withId($id);
        }

        return $file;
    }

    public function save(StoredFile $file): void
    {
        $this->entityManager->persist($file);
        $this->entityManager->flush();
    }

    public function remove(StoredFile $file): void
    {
        $this->entityManager->remove($file);
        $this->entityManager->flush();
    }
}
