<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Shared\InvalidArgument;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
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
     * Имя типа в конфиге Doctrine; DBAL 4 сам его больше не спрашивает,
     * но в сообщениях об ошибках оно нужно.
     */
    abstract public function getName(): string;

    /**
     * @return T
     */
    abstract protected function fromDatabase(string $value): \Stringable;

    /**
     * @return class-string<T>
     */
    abstract protected function valueObjectClass(): string;

    /**
     * @return T|null
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\Stringable
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof ($this->valueObjectClass())) {
            return $value;
        }

        if (!is_string($value) && !$value instanceof \Stringable) {
            throw InvalidType::new($value, $this->getName(), ['string', $this->valueObjectClass()]);
        }

        try {
            return $this->fromDatabase((string) $value);
        } catch (InvalidArgument $e) {
            // В базе лежит то, что домен считает невозможным — это порча данных,
            // а не ошибка пользователя
            throw ValueNotConvertible::new((string) $value, $this->getName(), $e->getMessage(), $e);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof \Stringable) {
            throw InvalidType::new($value, $this->getName(), [$this->valueObjectClass()]);
        }

        return (string) $value;
    }
}
