<?php

declare(strict_types=1);

namespace App\Domain\Drawing;

use App\Domain\Note\ValueObject\NoteId;

/**
 * Порт хранилища рисунков. Отсутствие рисунка — нормальное состояние
 * (на заметке ещё не рисовали), поэтому find возвращает null, а не бросает.
 */
interface DrawingRepository
{
    public function find(NoteId $noteId): ?Drawing;

    public function save(Drawing $drawing): void;

    /**
     * Убирает рисунок вместе с заметкой; без рисунка — ничего не делает.
     */
    public function removeFor(NoteId $noteId): void;
}
