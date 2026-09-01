<?php

declare(strict_types=1);

namespace App\UI\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Разбор тела запроса. Дальше по стеку ходят уже команды приложения,
 * а не HTTP-объекты.
 */
final readonly class JsonPayload
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(private array $data)
    {
    }

    public static function of(Request $request): self
    {
        $raw = $request->getContent();

        if ('' === $raw) {
            return new self([]);
        }

        try {
            $decoded = json_decode($raw, true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new self([]);
        }

        return new self(\is_array($decoded) ? $decoded : []);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->data[$key] ?? null;

        return \is_scalar($value) ? (string) $value : $default;
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return \is_scalar($value) && '' !== (string) $value ? (string) $value : null;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->data[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }
}
