<?php

declare(strict_types=1);

namespace App\Application\Drawing\Query\GetDrawing;

final readonly class GetDrawingQuery
{
    public function __construct(public string $noteId)
    {
    }
}
