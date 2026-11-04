<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Storage;

use App\Application\Shared\Port\StorageUnavailable;
use App\Domain\File\ValueObject\MimeType;
use App\Domain\File\ValueObject\StorageKey;
use App\Domain\File\ValueObject\Visibility;
use App\Infrastructure\Storage\Flysystem\FlysystemFileStorage;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Адаптер проверяется на Flysystem в памяти: маршрутизация по бакетам
 * и перевод ошибок не зависят от того, S3 за ним или нет.
 */
#[CoversClass(FlysystemFileStorage::class)]
final class FlysystemFileStorageTest extends TestCase
{
    private Filesystem $public;
    private Filesystem $private;
    private FlysystemFileStorage $storage;

    protected function setUp(): void
    {
        $this->public = new Filesystem(new InMemoryFilesystemAdapter());
        $this->private = new Filesystem(new InMemoryFilesystemAdapter());
        $this->storage = new FlysystemFileStorage($this->public, $this->private, '/files/');
    }

    public function testPublicKeyGoesToPublicBucketOnly(): void
    {
        $key = new StorageKey(Visibility::Public, '2026/11/a.png');

        $this->storage->store($key, 'png', MimeType::fromString('image/png'));

        self::assertTrue($this->public->fileExists('2026/11/a.png'));
        self::assertFalse($this->private->fileExists('2026/11/a.png'));
        self::assertSame('png', $this->storage->read($key));
    }

    public function testPrivateKeyGoesToPrivateBucketOnly(): void
    {
        $key = new StorageKey(Visibility::Private, '2026/11/b.pdf');

        $this->storage->store($key, 'pdf', MimeType::fromString('application/pdf'));

        self::assertTrue($this->private->fileExists('2026/11/b.pdf'));
        self::assertFalse($this->public->fileExists('2026/11/b.pdf'));
        self::assertSame('pdf', $this->storage->read($key));
    }

    public function testMissingObjectIsReportedAsStorageFailure(): void
    {
        $this->expectException(StorageUnavailable::class);
        $this->expectExceptionMessage('прочитать');

        $this->storage->read(new StorageKey(Visibility::Public, 'nope.png'));
    }

    public function testRemoveIsIdempotent(): void
    {
        $key = new StorageKey(Visibility::Public, '2026/11/c.txt');
        $this->storage->store($key, 'txt', MimeType::fromString('text/plain'));

        $this->storage->remove($key);
        $this->storage->remove($key);

        self::assertFalse($this->public->fileExists('2026/11/c.txt'));
    }

    public function testPublicUrlOnlyForPublicKey(): void
    {
        self::assertSame(
            '/files/2026/11/a.png',
            $this->storage->publicUrl(new StorageKey(Visibility::Public, '2026/11/a.png')),
            'лишний слэш базового адреса не удваивается',
        );
        self::assertNull($this->storage->publicUrl(new StorageKey(Visibility::Private, '2026/11/a.png')));
    }
}
