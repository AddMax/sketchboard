<?php

declare(strict_types=1);

namespace App\Domain\Note;

use App\Domain\Note\Exception\NoteNotFound;
use App\Domain\Note\ValueObject\NoteId;

/**
 * Порт хранилища. Домен объявляет, что ему нужно; чем это обеспечено —
 * Doctrine, файлом или памятью в тесте — его не касается.
 */
interface NoteRepository
{
    public function nextIdentity(): NoteId;

    /**
     * @throws NoteNotFound
     */
    public function get(NoteId $id): Note;

    public function save(Note $note): void;

    public function remove(Note $note): void;

    /**
     * Последние заметки доски, свежие первыми.
     *
     * @return list<Note>
     */
    public function latest(int $limit = 200): array;
}
