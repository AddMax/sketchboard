<?php

declare(strict_types=1);

namespace App\Domain\Drawing\ValueObject;

/**
 * Элемент рисунка: штрих кисти или фигура. Общего у них немного —
 * идентификатор от клиента и сериализация в JSON с полем type,
 * по которому элемент разбирается обратно.
 */
interface Element
{
    public function id(): string;

    /**
     * @return array<string, mixed> с обязательным ключом type
     */
    public function toArray(): array;
}
