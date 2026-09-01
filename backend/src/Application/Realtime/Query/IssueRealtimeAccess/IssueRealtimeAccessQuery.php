<?php

declare(strict_types=1);

namespace App\Application\Realtime\Query\IssueRealtimeAccess;

final readonly class IssueRealtimeAccessQuery
{
    public function __construct(public string $participant)
    {
    }
}
