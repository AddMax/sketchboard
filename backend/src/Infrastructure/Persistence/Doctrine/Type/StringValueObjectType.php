<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Shared\InvalidArgument;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Общая часть для объектов-значений, которые хранятся одной строковой
 * колонкой. Наследнику остаётся сказать, как значение восстановить.
 *
 * @template T of \Stringable
 */
abstract class StringValueObjectType extends Type
{
    /**
     * @return T
     */
    abstract protected function fromDatabase(string $value): \Stringable;

    /**
     * @return class-string<T>
     */
    abstract protected function valueObjectClass(): string;

    public function convertToPHPValue($value, AbstractPlatform $platform): ?\Stringable
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof ($this->valueObjectClass())) {
            return $value;
        }

        try {
            return $this->fromDatabase((string) $value);
        } catch (InvalidArgument $e) {
            // В базе лежит то, что домен считает невозможным — это порча данных,
            // а не ошибка пользователя
            throw ConversionException::conversionFailed((string) $value, $this->getName(), $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        return (string) $value;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
