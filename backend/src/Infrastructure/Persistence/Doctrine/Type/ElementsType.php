<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Drawing\ValueObject\Elements;
use App\Domain\Shared\InvalidArgument;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Элементы рисунка хранятся одним JSON-документом: читаются и пишутся всегда
 * целиком, запросов «по точкам» нет — отдельная таблица только усложнила бы сохранение.
 */
final class ElementsType extends Type
{
    public const string NAME = 'drawing_elements';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Elements
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof Elements) {
            return $value;
        }

        // PostgreSQL отдаёт json строкой, но драйверы вправе вернуть и поток
        $json = is_resource($value) ? stream_get_contents($value) : $value;

        if (!is_string($json)) {
            throw InvalidType::new($value, self::NAME, ['string', 'resource']);
        }

        try {
            return Elements::fromArray(json_decode($json, true, 8, JSON_THROW_ON_ERROR));
        } catch (\JsonException|InvalidArgument $e) {
            // В базе лежит то, что домен считает невозможным — порча данных
            throw ValueNotConvertible::new($json, self::NAME, $e->getMessage(), $e);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Elements) {
            throw InvalidType::new($value, self::NAME, [Elements::class]);
        }

        return json_encode($value->toArray(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }
}
