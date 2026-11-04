<?php

declare(strict_types=1);

namespace App\Domain\File;

use App\Domain\File\ValueObject\FileId;
use App\Domain\File\ValueObject\FileSize;
use App\Domain\File\ValueObject\MimeType;
use App\Domain\File\ValueObject\OriginalName;
use App\Domain\File\ValueObject\StorageKey;
use App\Domain\File\ValueObject\Visibility;
use App\Domain\Note\ValueObject\Author;

/**
 * Корень агрегата «загруженный файл»: метаданные и правила доступа.
 * Сами байты живут в хранилище под ключом, который агрегат вычисляет
 * сам, — база хранит только описание.
 *
 * Владелец — тот же Author, что и у заметки: участника в системе пока
 * одна сущность, и заводить второй тип для того же имени нет причин.
 * Событий файл не порождает: по realtime-каналу он не летает.
 */
class StoredFile
{
    private function __construct(
        private readonly FileId $id,
        private readonly OriginalName $name,
        private readonly MimeType $mimeType,
        private readonly FileSize $size,
        private readonly Visibility $visibility,
        private readonly Author $owner,
        private readonly \DateTimeImmutable $uploadedAt,
    ) {
    }

    public static function upload(
        FileId $id,
        OriginalName $name,
        MimeType $mimeType,
        FileSize $size,
        Visibility $visibility,
        Author $owner,
        ?\DateTimeImmutable $uploadedAt = null,
    ): self {
        return new self($id, $name, $mimeType, $size, $visibility, $owner, $uploadedAt ?? new \DateTimeImmutable());
    }

    /**
     * Публичный файл читает кто угодно, приватный — только владелец.
     * Пока участник не аутентифицирован, это правило слабее, чем выглядит,
     * но место проверки уже здесь, а не в контроллере.
     */
    public function canBeReadBy(Author $reader): bool
    {
        return $this->visibility->isPublic() || $this->isOwnedBy($reader);
    }

    /**
     * Удалять файл может только владелец, какой бы ни была видимость.
     */
    public function isOwnedBy(Author $participant): bool
    {
        return $this->owner->toString() === $participant->toString();
    }

    /**
     * Ключ в хранилище: бакет по видимости, внутри — год/месяц загрузки
     * и идентификатор. Расширение берётся из MIME, а не из имени файла.
     */
    public function storageKey(): StorageKey
    {
        return new StorageKey(
            $this->visibility,
            sprintf('%s/%s.%s', $this->uploadedAt->format('Y/m'), $this->id->toString(), $this->mimeType->extension()),
        );
    }

    public function id(): FileId
    {
        return $this->id;
    }

    public function name(): OriginalName
    {
        return $this->name;
    }

    public function mimeType(): MimeType
    {
        return $this->mimeType;
    }

    public function size(): FileSize
    {
        return $this->size;
    }

    public function visibility(): Visibility
    {
        return $this->visibility;
    }

    public function owner(): Author
    {
        return $this->owner;
    }

    public function uploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }
}
