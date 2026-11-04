<?php

declare(strict_types=1);

namespace App\Domain\Drawing\ValueObject;

use App\Domain\Shared\InvalidArgument;

/**
 * Элемент сцены Excalidraw. Геометрия, стиль и служебные поля редактора
 * (version, seed, index…) принадлежат ему и хранятся как есть: домен не
 * пересказывает чужую модель, а стережёт только то, без чего элемент
 * нельзя ни сохранить, ни показать, — идентификатор, тип и размер.
 */
final readonly class Element
{
    public const int MAX_ID_LENGTH = 64;
    public const int MAX_TYPE_LENGTH = 32;
    /** Штрих и ломаная хранят точки списком; это единственный массив в элементе, который растёт без предела. */
    public const int MAX_POINTS = 20_000;

    /**
     * Картинка в Excalidraw — ссылка на файл, который хранится отдельно
     * от сцены: в поле fileId лежит идентификатор StoredFile. Сцена знает
     * только ссылку, сам файл живёт в хранилище и удаляется вместе с заметкой.
     */
    public const string TYPE_IMAGE = 'image';

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        private string $id,
        private string $type,
        private array $data,
    ) {
    }

    /**
     * @param mixed $data ожидается объект элемента Excalidraw с полями id и type
     */
    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || array_is_list($data)) {
            throw new InvalidArgument('Элемент рисунка задаётся объектом с полями id и type', 'elements');
        }

        $id = $data['id'] ?? null;

        if (!is_string($id) || '' === trim($id) || mb_strlen($id) > self::MAX_ID_LENGTH) {
            throw new InvalidArgument(sprintf('У элемента должен быть идентификатор не длиннее %d символов', self::MAX_ID_LENGTH), 'elements');
        }

        $type = $data['type'] ?? null;

        if (!is_string($type) || 1 !== preg_match('/^[a-z]{1,'.self::MAX_TYPE_LENGTH.'}$/', $type)) {
            throw new InvalidArgument('Тип элемента задаётся строчными латинскими буквами', 'elements');
        }

        // Удалённые элементы Excalidraw держит в сцене для слияния правок;
        // в хранилище им делать нечего — клиент обязан отбросить их до отправки
        if (true === ($data['isDeleted'] ?? false)) {
            throw new InvalidArgument('Удалённые элементы не сохраняются', 'elements');
        }

        $points = $data['points'] ?? null;

        if (null !== $points && (!is_array($points) || count($points) > self::MAX_POINTS)) {
            throw new InvalidArgument(sprintf('Элемент не может содержать больше %d точек', self::MAX_POINTS), 'elements');
        }

        /** @var array<string, mixed> $data */
        return new self($id, $type, $data);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * Идентификатор файла картинки; у остальных элементов и у картинки
     * без файла (редактор ещё не загрузил его) — null.
     */
    public function fileId(): ?string
    {
        if (self::TYPE_IMAGE !== $this->type) {
            return null;
        }

        $fileId = $this->data['fileId'] ?? null;

        return is_string($fileId) && '' !== $fileId ? $fileId : null;
    }

    /**
     * @return array<string, mixed> элемент в том виде, в каком его прислал редактор
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
