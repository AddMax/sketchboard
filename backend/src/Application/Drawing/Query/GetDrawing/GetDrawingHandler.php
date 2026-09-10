<?php

declare(strict_types=1);

namespace App\Application\Drawing\Query\GetDrawing;

use App\Application\Drawing\ReadModel\DrawingView;
use App\Domain\Drawing\DrawingRepository;
use App\Domain\Note\NoteRepository;
use App\Domain\Note\ValueObject\NoteId;

final readonly class GetDrawingHandler
{
    public function __construct(
        private NoteRepository $notes,
        private DrawingRepository $drawings,
    ) {
    }

    public function __invoke(GetDrawingQuery $query): DrawingView
    {
        $noteId = NoteId::fromString($query->noteId);

        // Для удалённой заметки отвечаем 404, а не пустым листом
        $this->notes->get($noteId);

        $drawing = $this->drawings->find($noteId);

        return null === $drawing ? DrawingView::blank($noteId) : DrawingView::fromDrawing($drawing);
    }
}
