<?php

declare(strict_types=1);

namespace App\Application\Shared\Port;

final readonly class RealtimeCredentials implements \JsonSerializable
{
    public function __construct(
        public string $token,
        public string $channel,
        public \DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * @return array<string, string|int>
     */
    public function jsonSerialize(): array
    {
        return [
            'token' => $this->token,
            'channel' => $this->channel,
            'expiresAt' => $this->expiresAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
