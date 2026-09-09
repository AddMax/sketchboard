<?php

declare(strict_types=1);

namespace App\Application\Shared\Port;

/**
 * Порт доступа к realtime-каналу: браузеру нужен способ подключиться,
 * а чем именно он подключится — деталь инфраструктуры.
 */
interface RealtimeAccess
{
    /**
     * Учётные данные для подключения конкретного участника.
     */
    public function credentialsFor(string $participant): RealtimeCredentials;
}
