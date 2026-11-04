<?php

declare(strict_types=1);

namespace App\Application\File\ReadModel;

use App\Domain\File\StoredFile;

/**
 * Описание файла для внешнего мира. `url` есть только у публичного файла:
 * приватный читается через API, и этот маршрут — забота UI, а не модели.
 */
final readonly class FileView implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $mimeType,
        public int $size,
        public string $visibility,
        public string $owner,
        public ?string $url,
        public string $uploadedAt,
    ) {
    }

    public static function fromFile(StoredFile $file, ?string $url): self
    {
        return new self(
            id: $file->id()->toString(),
            name: $file->name()->toString(),
            mimeType: $file->mimeType()->toString(),
            size: $file->size()->bytes(),
            visibility: $file->visibility()->value,
            owner: $file->owner()->toString(),
            url: $url,
            uploadedAt: $file->uploadedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @return array<string, string|int|null>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'visibility' => $this->visibility,
            'owner' => $this->owner,
            'url' => $this->url,
            'uploadedAt' => $this->uploadedAt,
        ];
    }
}
