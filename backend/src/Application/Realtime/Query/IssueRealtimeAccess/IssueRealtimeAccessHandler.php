<?php

declare(strict_types=1);

namespace App\Application\Realtime\Query\IssueRealtimeAccess;

use App\Application\Shared\Port\RealtimeAccess;
use App\Application\Shared\Port\RealtimeCredentials;

/**
 * Выдаёт браузеру данные для подключения к каналу доски.
 *
 * Здесь же со временем появится проверка прав: пускать ли участника
 * на эту доску вообще.
 */
final readonly class IssueRealtimeAccessHandler
{
    public function __construct(private RealtimeAccess $realtime)
    {
    }

    public function __invoke(IssueRealtimeAccessQuery $query): RealtimeCredentials
    {
        return $this->realtime->credentialsFor($query->participant);
    }
}
