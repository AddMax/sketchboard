<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Drawing\Drawing;
use App\Domain\Drawing\DrawingRepository;
use App\Domain\Note\ValueObject\NoteId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineDrawingRepository implements DrawingRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(NoteId $noteId): ?Drawing
    {
        return $this->entityManager->find(Drawing::class, $noteId);
    }

    public function save(Drawing $drawing): void
    {
        $this->entityManager->persist($drawing);
        $this->entityManager->flush();
    }

    public function removeFor(NoteId $noteId): void
    {
        $drawing = $this->find($noteId);

        if (null === $drawing) {
            return;
        }

        $this->entityManager->remove($drawing);
        $this->entityManager->flush();
    }
}
