<?php

declare(strict_types=1);

namespace App\Domain\Note;

use App\Domain\Note\Event\NoteWasCreated;
use App\Domain\Note\Event\NoteWasDeleted;
use App\Domain\Note\Event\NoteWasMoved;
use App\Domain\Note\ValueObject\Author;
use App\Domain\Note\ValueObject\Color;
use App\Domain\Note\ValueObject\NoteId;
use App\Domain\Note\ValueObject\NoteText;
use App\Domain\Note\ValueObject\Position;
use App\Domain\Shared\DomainEvent;

/**
 * Корень агрегата «заметка на доске».
 *
 * Класс ничего не знает ни о Doctrine, ни о Symfony: маппинг задан XML-файлом
 * в инфраструктурном слое, а инварианты стерегут объекты-значения. Благодаря
 * этому агрегат тестируется без базы и контейнера.
 */
class Note
{
    /** @var list<DomainEvent> события, ещё не отданные наружу */
    private array $recordedEvents = [];

    private function __construct(
        private readonly NoteId $id,
        private NoteText $text,
        private Position $position,
        private readonly Color $color,
        private readonly Author $author,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Появление новой заметки на доске.
     */
    public static function write(
        NoteId $id,
        NoteText $text,
        Position $position,
        Color $color,
        Author $author,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        $note = new self($id, $text, $position, $color, $author, $createdAt ?? new \DateTimeImmutable());
        $note->recordThat(new NoteWasCreated($note));

        return $note;
    }

    public function moveTo(Position $position): void
    {
        // Перемещение «на то же место» — не изменение: не тревожим подписчиков
        if ($this->position->equals($position)) {
            return;
        }

        $this->position = $position;
        $this->recordThat(new NoteWasMoved($this));
    }

    /**
     * Отмечает удаление. Само удаление выполняет репозиторий — агрегат лишь
     * фиксирует факт, чтобы о нём узнали остальные участники доски.
     */
    public function delete(): void
    {
        $this->recordThat(new NoteWasDeleted($this->id));
    }

    public function id(): NoteId
    {
        return $this->id;
    }

    public function text(): NoteText
    {
        return $this->text;
    }

    public function position(): Position
    {
        return $this->position;
    }

    public function color(): Color
    {
        return $this->color;
    }

    public function author(): Author
    {
        return $this->author;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Отдаёт накопленные события и очищает их: каждое публикуется ровно раз.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    private function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
