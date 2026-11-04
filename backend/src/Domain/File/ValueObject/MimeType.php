<?php

declare(strict_types=1);

namespace App\Domain\File\ValueObject;

use App\Domain\Shared\InvalidArgument;

/**
 * Тип содержимого. Список закрытый: сервер хранит то, что умеет отдать
 * браузеру безопасно, — картинки, PDF и простой текст. Расширение для
 * ключа в хранилище берётся отсюда же, а не из имени файла: имени
 * пользователя мы не верим.
 */
final readonly class MimeType implements \Stringable
{
    public const int MAX_LENGTH = 127;

    /** @var array<string, string> MIME → расширение в ключе хранилища */
    private const array ALLOWED = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
    ];

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        // Параметры вроде «; charset=utf-8» к типу не относятся
        $value = strtolower(trim(explode(';', $value, 2)[0]));

        if (!isset(self::ALLOWED[$value])) {
            throw new InvalidArgument(
                sprintf('Файлы типа «%s» не принимаются; допустимы: %s', $value, implode(', ', self::allowed())),
                'mimeType',
            );
        }

        return new self($value);
    }

    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        return array_keys(self::ALLOWED);
    }

    public function extension(): string
    {
        return self::ALLOWED[$this->value];
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
