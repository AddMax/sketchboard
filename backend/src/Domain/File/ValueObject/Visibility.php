<?php

declare(strict_types=1);

namespace App\Domain\File\ValueObject;

use App\Domain\Shared\InvalidArgument;

/**
 * Кому виден файл. Публичный читается по прямой ссылке без проверок,
 * приватный — только через бэкенд и только владельцем.
 */
enum Visibility: string
{
    case Public = 'public';
    case Private = 'private';

    public static function fromString(string $value): self
    {
        $value = strtolower(trim($value));

        return self::tryFrom($value) ?? throw new InvalidArgument(
            sprintf('Видимость файла — %s, а не «%s»', implode(' или ', array_column(self::cases(), 'value')), $value),
            'visibility',
        );
    }

    public function isPublic(): bool
    {
        return self::Public === $this;
    }
}
