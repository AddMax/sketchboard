<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Drawing\ValueObject\Strokes;
use App\Domain\Shared\InvalidArgument;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Штрихи хранятся одним JSON-документом: читаются и пишутся всегда целиком,
 * запросов «по точкам» нет — отдельная таблица только усложнила бы сохранение.
 */
final class StrokesType extends Type
{
    public const string NAME = 'drawing_strokes';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?Strokes
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof Strokes) {
            return $value;
        }

        try {
            $decoded = json_decode(\is_resource($value) ? (string) stream_get_contents($value) : (string) $value, true, 8, \JSON_THROW_ON_ERROR);

            return Strokes::fromArray($decoded);
        } catch (\JsonException|InvalidArgument $e) {
            // В базе лежит то, что домен считает невозможным — порча данных
            throw ConversionException::conversionFailed('<json>', $this->getName(), $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Strokes) {
            throw ConversionException::conversionFailedInvalidType($value, $this->getName(), [Strokes::class]);
        }

        return json_encode($value->toArray(), \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
    }
}
