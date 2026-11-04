<?php

declare(strict_types=1);

namespace App\Domain\File\ValueObject;

use App\Domain\Shared\InvalidArgument;

/**
 * Имя файла, каким его загрузил пользователь. Нужно только для показа и
 * заголовка Content-Disposition: в ключе хранилища оно не участвует,
 * поэтому достаточно отрезать путь и запретить управляющие символы.
 */
final readonly class OriginalName implements \Stringable
{
    public const int MAX_LENGTH = 255;

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        // Браузеры отдают только имя, но клиент может прислать и путь
        $segments = explode('/', str_replace('\\', '/', $value));
        $value = trim(end($segments));

        if ('' === $value || '.' === $value || '..' === $value) {
            throw new InvalidArgument('У файла должно быть имя', 'name');
        }

        if (1 === preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new InvalidArgument('Имя файла содержит управляющие символы', 'name');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidArgument(
                sprintf('Имя файла длиннее %d символов', self::MAX_LENGTH),
                'name',
            );
        }

        return new self($value);
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
