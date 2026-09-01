<?php

declare(strict_types=1);

namespace App\Application\Note\Query\ListNotes;

use App\Application\Note\ReadModel\NoteView;
use App\Domain\Note\Note;
use App\Domain\Note\NoteRepository;

final readonly class ListNotesHandler
{
    public function __construct(private NoteRepository $notes)
    {
    }

    /**
     * @return list<NoteView>
     */
    public function __invoke(ListNotesQuery $query): array
    {
        return array_map(
            static fn (Note $note) => NoteView::fromNote($note),
            $this->notes->latest($query->limit),
        );
    }
}
