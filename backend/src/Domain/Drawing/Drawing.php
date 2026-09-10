<?php

declare(strict_types=1);

namespace App\Domain\Drawing;

use App\Domain\Drawing\ValueObject\Strokes;
use App\Domain\Note\ValueObject\NoteId;

/**
 * Рисунок, прикреплённый к заметке: отдельный агрегат, а не поле Note.
 * Заметка летает по realtime-каналу целиком при каждом изменении,
 * а рисунок может весить сотни килобайт — им обмениваться так нельзя.
 * Идентификатор общий с заметкой: рисунок на заметку один.
 */
class Drawing
{
    private function __construct(
        private readonly NoteId $noteId,
        private Strokes $strokes,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Чистый лист для заметки, у которой рисунка ещё не было.
     */
    public static function startFor(NoteId $noteId, ?\DateTimeImmutable $at = null): self
    {
        return new self($noteId, Strokes::none(), $at ?? new \DateTimeImmutable());
    }

    /**
     * Заменяет содержимое целиком. Повторная отправка того же состояния
     * (ретрай клиента) не должна двигать время изменения.
     */
    public function replaceStrokes(Strokes $strokes, ?\DateTimeImmutable $at = null): void
    {
        if ($this->strokes->equals($strokes)) {
            return;
        }

        $this->strokes = $strokes;
        $this->updatedAt = $at ?? new \DateTimeImmutable();
    }

    public function noteId(): NoteId
    {
        return $this->noteId;
    }

    public function strokes(): Strokes
    {
        return $this->strokes;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
