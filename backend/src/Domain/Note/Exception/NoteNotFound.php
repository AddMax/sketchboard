<?php

declare(strict_types=1);

namespace App\Domain\Note\Exception;

use App\Domain\Note\ValueObject\NoteId;

final class NoteNotFound extends \DomainException
{
    public static function withId(NoteId $id): self
    {
        return new self(sprintf('Заметка %s не найдена', $id->toString()));
    }
}
