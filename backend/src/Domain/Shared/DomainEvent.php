<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Факт, случившийся в домене. Имя события — часть общего языка проекта,
 * поэтому живёт здесь; способ сериализации — уже деталь инфраструктуры.
 */
interface DomainEvent
{
    public function eventName(): string;

    public function occurredAt(): \DateTimeImmutable;
}
