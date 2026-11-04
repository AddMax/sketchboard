<?php

declare(strict_types=1);

namespace App\Domain\File\ValueObject;

use App\Domain\Shared\InvalidArgument;

/**
 * Размер файла в байтах. Предел меньше лимита php.ini и nginx (32 МБ):
 * правило должно срабатывать в домене, а не обрывом загрузки.
 */
final readonly class FileSize
{
    public const int MAX_BYTES = 10 * 1024 * 1024;

    private function __construct(private int $bytes)
    {
    }

    public static function ofBytes(int $bytes): self
    {
        if ($bytes <= 0) {
            throw new InvalidArgument('Файл пуст', 'file');
        }

        if ($bytes > self::MAX_BYTES) {
            throw new InvalidArgument(
                sprintf('Файл больше %d МБ', intdiv(self::MAX_BYTES, 1024 * 1024)),
                'file',
            );
        }

        return new self($bytes);
    }

    public function bytes(): int
    {
        return $this->bytes;
    }

    public function equals(self $other): bool
    {
        return $this->bytes === $other->bytes;
    }
}
