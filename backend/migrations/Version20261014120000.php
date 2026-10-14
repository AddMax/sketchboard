<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Рисунок стал сценой Excalidraw. Старые штрихи и фигуры переводятся в его
 * элементы здесь, а не при чтении: домен больше не знает прежнего формата.
 */
final class Version20261014120000 extends AbstractMigration
{
    /** Excalidraw рисует кисть через perfect-freehand с size = strokeWidth × 4.25. */
    private const float FREEDRAW_SIZE_FACTOR = 4.25;

    public function getDescription(): string
    {
        return 'Рисунки хранят сцену Excalidraw; прежние штрихи и фигуры конвертируются';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE drawings RENAME COLUMN lines TO elements');
    }

    public function postUp(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT note_id, elements FROM drawings');

        foreach ($rows as $row) {
            $elements = json_decode((string) $row['elements'], true, 32, JSON_THROW_ON_ERROR);

            if (!is_array($elements) || !self::isLegacy($elements)) {
                continue;
            }

            $this->connection->update(
                'drawings',
                ['elements' => json_encode(array_map(self::convert(...), $elements), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)],
                ['note_id' => $row['note_id']],
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Сконвертированные элементы обратно не переводятся: прежний формат беднее
        $this->addSql('ALTER TABLE drawings RENAME COLUMN elements TO lines');
    }

    /**
     * @param array<mixed> $elements
     */
    private static function isLegacy(array $elements): bool
    {
        foreach ($elements as $element) {
            if (!is_array($element)) {
                return false;
            }

            // Элемент Excalidraw всегда несёт version; у штрихов и фигур его не было
            if (!array_key_exists('version', $element)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $element
     *
     * @return array<string, mixed>
     */
    private static function convert(array $element): array
    {
        // Записи без type — штрихи, сохранённые до появления фигур
        return match ($element['type'] ?? 'line') {
            'shape' => self::shape($element),
            default => self::freedraw($element),
        };
    }

    /**
     * @param array<string, mixed> $line
     *
     * @return array<string, mixed>
     */
    private static function freedraw(array $line): array
    {
        $points = array_values($line['points']);
        $xs = array_map(static fn (array $point): float => (float) $point['x'], $points);
        $ys = array_map(static fn (array $point): float => (float) $point['y'], $points);
        $minX = min($xs);
        $minY = min($ys);

        return self::base($line['id'], 'freedraw', $minX, $minY, max($xs) - $minX, max($ys) - $minY, $line['color'], (float) $line['width'] / self::FREEDRAW_SIZE_FACTOR, null) + [
            // Точки Excalidraw хранит относительно левого верхнего угла элемента
            'points' => array_map(static fn (float $x, float $y): array => [$x - $minX, $y - $minY], $xs, $ys),
            'pressures' => [],
            'simulatePressure' => true,
            'lastCommittedPoint' => null,
        ];
    }

    /**
     * @param array<string, mixed> $shape
     *
     * @return array<string, mixed>
     */
    private static function shape(array $shape): array
    {
        $width = (float) $shape['width'];
        $height = (float) $shape['height'];
        $base = self::base($shape['id'], 'rectangle', (float) $shape['x'], (float) $shape['y'], $width, $height, $shape['strokeColor'], (float) $shape['strokeWidth'], $shape['fill'] ?? null);

        return match ($shape['kind']) {
            'ellipse' => ['type' => 'ellipse'] + $base,
            // Треугольника в Excalidraw нет: это замкнутая ломаная с вершиной наверху
            'triangle' => ['type' => 'line'] + $base + [
                'points' => [[$width / 2, 0.0], [$width, $height], [0.0, $height], [$width / 2, 0.0]],
                'lastCommittedPoint' => null,
                'startBinding' => null,
                'endBinding' => null,
                'startArrowhead' => null,
                'endArrowhead' => null,
            ],
            default => $base,
        };
    }

    /**
     * Общие поля элемента Excalidraw. Чего здесь нет, редактор дополнит
     * значениями по умолчанию при загрузке сцены.
     *
     * @return array<string, mixed>
     */
    private static function base(string $id, string $type, float $x, float $y, float $width, float $height, string $strokeColor, float $strokeWidth, ?string $fill): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'angle' => 0,
            'strokeColor' => $strokeColor,
            'backgroundColor' => $fill ?? 'transparent',
            'fillStyle' => 'solid',
            'strokeWidth' => round($strokeWidth, 2),
            'strokeStyle' => 'solid',
            // Прежнее полотно рисовало ровно, без эскизной дрожи
            'roughness' => 0,
            'opacity' => 100,
            'roundness' => null,
            'seed' => random_int(1, 2 ** 31 - 1),
            'version' => 1,
            'versionNonce' => random_int(1, 2 ** 31 - 1),
            'index' => null,
            'isDeleted' => false,
            'groupIds' => [],
            'frameId' => null,
            'boundElements' => null,
            'updated' => (int) (microtime(true) * 1000),
            'link' => null,
            'locked' => false,
        ];
    }
}
