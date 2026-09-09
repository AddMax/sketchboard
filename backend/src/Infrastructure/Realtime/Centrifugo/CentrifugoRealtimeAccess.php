<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime\Centrifugo;

use App\Application\Shared\Port\RealtimeAccess;
use App\Application\Shared\Port\RealtimeCredentials;
use Firebase\JWT\JWT;

/**
 * Адаптер доступа к каналу: выдаёт JWT подключения, который Centrifugo
 * проверяет своим общим секретом.
 *
 * Секрет остаётся на сервере, браузер получает только короткоживущий токен.
 */
final readonly class CentrifugoRealtimeAccess implements RealtimeAccess
{
    public function __construct(
        private string $centrifugoTokenSecret,
        private string $realtimeChannel,
        private int $centrifugoTokenTtl,
    ) {
    }

    public function credentialsFor(string $participant): RealtimeCredentials
    {
        $issuedAt = new \DateTimeImmutable();
        $expiresAt = $issuedAt->modify(sprintf('+%d seconds', $this->centrifugoTokenTtl));

        $token = JWT::encode([
            // sub — идентификатор участника в терминах Centrifugo
            'sub' => $participant,
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
        ], $this->centrifugoTokenSecret, 'HS256');

        return new RealtimeCredentials($token, $this->realtimeChannel, $expiresAt);
    }
}
