<?php

declare(strict_types=1);

namespace App\Application\Drawing\Command\SaveDrawing;

final readonly class SaveDrawingCommand
{
    /**
     * @param list<mixed> $elements сырой список элементов из запроса; форму проверит домен
     */
    public function __construct(
        public string $noteId,
        public array $elements,
    ) {
    }
}
