<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\File;

use App\Domain\File\StoredFile;
use App\Domain\File\ValueObject\FileId;
use App\Domain\File\ValueObject\FileSize;
use App\Domain\File\ValueObject\MimeType;
use App\Domain\File\ValueObject\OriginalName;
use App\Domain\File\ValueObject\StorageKey;
use App\Domain\File\ValueObject\Visibility;
use App\Domain\Note\ValueObject\Author;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoredFile::class)]
#[CoversClass(StorageKey::class)]
final class StoredFileTest extends TestCase
{
    public function testStorageKeyIsDerivedFromUploadTimeIdAndMime(): void
    {
        $id = FileId::fromString('019a4f3e-5d0e-7b2c-9f1a-3c2b1a0f9e8d');
        $file = $this->file(
            id: $id,
            name: 'отчёт.PDF.exe',
            mimeType: 'image/jpeg',
            uploadedAt: new \DateTimeImmutable('2026-11-04 12:00:00'),
        );

        $key = $file->storageKey();

        // Расширение — из MIME, а не из имени: имени пользователя не верим
        self::assertSame('2026/11/019a4f3e-5d0e-7b2c-9f1a-3c2b1a0f9e8d.jpg', $key->path);
        self::assertSame(Visibility::Private, $key->visibility);
    }

    public function testPublicFileIsReadableByAnyone(): void
    {
        $file = $this->file(visibility: Visibility::Public, owner: 'Аня');

        self::assertTrue($file->canBeReadBy(Author::fromString('Боря')));
        self::assertTrue($file->canBeReadBy(Author::anonymous()));
    }

    public function testPrivateFileIsReadableOnlyByOwner(): void
    {
        $file = $this->file(visibility: Visibility::Private, owner: 'Аня');

        self::assertTrue($file->canBeReadBy(Author::fromString('Аня')));
        self::assertFalse($file->canBeReadBy(Author::fromString('Боря')));
        self::assertFalse($file->canBeReadBy(Author::anonymous()));
    }

    public function testOnlyOwnerOwnsFileRegardlessOfVisibility(): void
    {
        $file = $this->file(visibility: Visibility::Public, owner: 'Аня');

        self::assertTrue($file->isOwnedBy(Author::fromString('Аня')));
        self::assertFalse($file->isOwnedBy(Author::fromString('Боря')));
    }

    public function testStorageKeysOfDifferentBucketsAreNotEqual(): void
    {
        $public = new StorageKey(Visibility::Public, '2026/11/a.png');
        $private = new StorageKey(Visibility::Private, '2026/11/a.png');

        self::assertFalse($public->equals($private));
        self::assertTrue($public->equals(new StorageKey(Visibility::Public, '2026/11/a.png')));
    }

    private function file(
        ?FileId $id = null,
        string $name = 'схема.png',
        string $mimeType = 'image/png',
        Visibility $visibility = Visibility::Private,
        string $owner = 'Аня',
        ?\DateTimeImmutable $uploadedAt = null,
    ): StoredFile {
        return StoredFile::upload(
            id: $id ?? FileId::generate(),
            name: OriginalName::fromString($name),
            mimeType: MimeType::fromString($mimeType),
            size: FileSize::ofBytes(1024),
            visibility: $visibility,
            owner: Author::fromString($owner),
            uploadedAt: $uploadedAt,
        );
    }
}
