<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Нарушение инварианта домена: значение не может существовать в таком виде.
 * Транспортный слой сам решает, каким кодом ответить.
 */
class InvalidArgument extends \DomainException
{
    public function __construct(
        string $message,
        private readonly string $field = '',
    ) {
        parent::__construct($message);
    }

    public function field(): string
    {
        return $this->field;
    }
}
