<?php

declare(strict_types=1);

namespace App\Application\Note\ReadModel;

use App\Domain\Note\Note;

/**
 * Представление заметки для внешнего мира. Отдельный тип нужен, чтобы
 * форма JSON-ответа не диктовалась структурой агрегата.
 */
final readonly class NoteView implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $text,
        public int $x,
        public int $y,
        public string $color,
        public string $author,
        public string $createdAt,
    ) {
    }

    public static function fromNote(Note $note): self
    {
        return new self(
            id: $note->id()->toString(),
            text: $note->text()->toString(),
            x: $note->position()->x(),
            y: $note->position()->y(),
            color: $note->color()->toString(),
            author: $note->author()->toString(),
            createdAt: $note->createdAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @return array<string, string|int>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'x' => $this->x,
            'y' => $this->y,
            'color' => $this->color,
            'author' => $this->author,
            'createdAt' => $this->createdAt,
        ];
    }
}
