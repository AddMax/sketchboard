<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Note\Exception\NoteNotFound;
use App\Domain\Note\Note;
use App\Domain\Note\NoteRepository;
use App\Domain\Note\ValueObject\NoteId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Единственное место, где домен встречается с Doctrine.
 */
final readonly class DoctrineNoteRepository implements NoteRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function nextIdentity(): NoteId
    {
        return NoteId::generate();
    }

    public function get(NoteId $id): Note
    {
        $note = $this->entityManager->find(Note::class, $id);

        if (null === $note) {
            throw NoteNotFound::withId($id);
        }

        return $note;
    }

    public function save(Note $note): void
    {
        $this->entityManager->persist($note);
        $this->entityManager->flush();
    }

    public function remove(Note $note): void
    {
        $this->entityManager->remove($note);
        $this->entityManager->flush();
    }

    public function latest(int $limit = 200): array
    {
        /** @var list<Note> $notes */
        $notes = $this->entityManager
            ->createQuery('SELECT n FROM '.Note::class.' n ORDER BY n.createdAt DESC')
            ->setMaxResults($limit)
            ->getResult();

        return $notes;
    }
}
