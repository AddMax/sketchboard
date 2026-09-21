<?php

declare(strict_types=1);

namespace App\Application\Drawing\ReadModel;

use App\Domain\Drawing\Drawing;
use App\Domain\Note\ValueObject\NoteId;

/**
 * Рисунок для внешнего мира. Пустой лист отдаём в той же форме,
 * что и сохранённый: клиенту не нужно различать «нет» и «пусто».
 */
final readonly class DrawingView implements \JsonSerializable
{
    /**
     * @param list<array<string, mixed>> $elements штрихи и фигуры в порядке наложения
     */
    public function __construct(
        public string $noteId,
        public array $elements,
        public ?string $updatedAt,
    ) {
    }

    public static function fromDrawing(Drawing $drawing): self
    {
        return new self(
            noteId: $drawing->noteId()->toString(),
            elements: $drawing->elements()->toArray(),
            updatedAt: $drawing->updatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    public static function blank(NoteId $noteId): self
    {
        return new self($noteId->toString(), [], null);
    }

    /**
     * @return array{noteId: string, elements: list<mixed>, updatedAt: ?string}
     */
    public function jsonSerialize(): array
    {
        return [
            'noteId' => $this->noteId,
            'elements' => $this->elements,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
