<?php

declare(strict_types=1);

namespace App\Application\Note\Command\CreateNote;

/**
 * Намерение пользователя, выраженное в примитивах: разбор транспорта
 * закончился, разбор домена ещё не начался.
 */
final readonly class CreateNoteCommand
{
    public function __construct(
        public string $text,
        public int $x,
        public int $y,
        public ?string $color = null,
        public ?string $author = null,
    ) {
    }
}
