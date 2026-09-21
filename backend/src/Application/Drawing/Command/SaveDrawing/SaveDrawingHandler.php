<?php

declare(strict_types=1);

namespace App\Application\Drawing\Command\SaveDrawing;

use App\Application\Drawing\ReadModel\DrawingView;
use App\Domain\Drawing\Drawing;
use App\Domain\Drawing\DrawingRepository;
use App\Domain\Drawing\ValueObject\Elements;
use App\Domain\Note\NoteRepository;
use App\Domain\Note\ValueObject\NoteId;

final readonly class SaveDrawingHandler
{
    public function __construct(
        private NoteRepository $notes,
        private DrawingRepository $drawings,
    ) {
    }

    public function __invoke(SaveDrawingCommand $command): DrawingView
    {
        $noteId = NoteId::fromString($command->noteId);

        // Рисунок без заметки существовать не может: get() бросит NoteNotFound
        $this->notes->get($noteId);

        $drawing = $this->drawings->find($noteId) ?? Drawing::startFor($noteId);
        $drawing->replaceElements(Elements::fromArray($command->elements));

        $this->drawings->save($drawing);

        return DrawingView::fromDrawing($drawing);
    }
}
