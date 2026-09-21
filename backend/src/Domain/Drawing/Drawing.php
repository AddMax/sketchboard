<?php

declare(strict_types=1);

namespace App\Domain\Drawing;

use App\Domain\Drawing\ValueObject\Elements;
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
        private Elements $elements,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Чистый лист для заметки, у которой рисунка ещё не было.
     */
    public static function startFor(NoteId $noteId, ?\DateTimeImmutable $at = null): self
    {
        return new self($noteId, Elements::none(), $at ?? new \DateTimeImmutable());
    }

    /**
     * Заменяет содержимое целиком. Повторная отправка того же состояния
     * (ретрай клиента) не должна двигать время изменения.
     */
    public function replaceElements(Elements $elements, ?\DateTimeImmutable $at = null): void
    {
        if ($this->elements->equals($elements)) {
            return;
        }

        $this->elements = $elements;
        $this->updatedAt = $at ?? new \DateTimeImmutable();
    }

    public function noteId(): NoteId
    {
        return $this->noteId;
    }

    public function elements(): Elements
    {
        return $this->elements;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
