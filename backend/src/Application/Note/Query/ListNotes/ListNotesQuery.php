<?php

declare(strict_types=1);

namespace App\Application\Note\Query\ListNotes;

final readonly class ListNotesQuery
{
    public function __construct(public int $limit = 200)
    {
    }
}
