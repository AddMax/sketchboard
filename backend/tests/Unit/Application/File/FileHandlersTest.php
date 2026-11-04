<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\File;

use App\Application\File\Command\DeleteFile\DeleteFileCommand;
use App\Application\File\Command\DeleteFile\DeleteFileHandler;
use App\Application\File\Command\UploadFile\UploadFileCommand;
use App\Application\File\Command\UploadFile\UploadFileHandler;
use App\Application\File\Query\DownloadFile\DownloadFileHandler;
use App\Application\File\Query\DownloadFile\DownloadFileQuery;
use App\Application\File\Query\GetFile\GetFileHandler;
use App\Application\File\Query\GetFile\GetFileQuery;
use App\Application\File\ReadModel\FileContent;
use App\Application\File\ReadModel\FileView;
use App\Domain\File\Exception\FileAccessDenied;
use App\Domain\File\Exception\FileNotFound;
use App\Domain\File\ValueObject\FileId;
use App\Domain\File\ValueObject\StorageKey;
use App\Domain\File\ValueObject\Visibility;
use App\Domain\Shared\InvalidArgument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Сценарии файла с адаптерами в памяти: порты заменяемы, домен тот же.
 */
#[CoversClass(UploadFileHandler::class)]
#[CoversClass(DeleteFileHandler::class)]
#[CoversClass(GetFileHandler::class)]
#[CoversClass(DownloadFileHandler::class)]
#[CoversClass(FileView::class)]
#[CoversClass(FileContent::class)]
final class FileHandlersTest extends TestCase
{
    private InMemoryFileRepository $files;
    private InMemoryFileStorage $storage;

    protected function setUp(): void
    {
        $this->files = new InMemoryFileRepository();
        $this->storage = new InMemoryFileStorage();
    }

    public function testUploadStoresBytesAndMetadata(): void
    {
        $view = $this->upload(visibility: 'public', owner: 'Аня');

        self::assertSame('схема.png', $view->name);
        self::assertSame('image/png', $view->mimeType);
        self::assertSame(strlen('байты'), $view->size, 'размер считается по содержимому, в байтах');
        self::assertSame('public', $view->visibility);
        self::assertSame('Аня', $view->owner);
        self::assertNotNull($view->url, 'у публичного файла есть прямая ссылка');
        self::assertStringEndsWith('.png', $view->url);
        self::assertSame(1, $this->files->count());
        self::assertSame(1, $this->storage->count());
    }

    public function testPrivateFileHasNoPublicUrl(): void
    {
        $view = $this->upload(visibility: 'private');

        self::assertNull($view->url);
    }

    public function testUploadWithoutOwnerIsAnonymous(): void
    {
        $view = $this->upload(owner: null);

        self::assertSame('anonymous', $view->owner);
    }

    public function testRejectedUploadLeavesNothingBehind(): void
    {
        try {
            $this->upload(mimeType: 'text/html');
            self::fail('html не должен приниматься');
        } catch (InvalidArgument $e) {
            self::assertSame('mimeType', $e->field());
        }

        self::assertSame(0, $this->files->count());
        self::assertSame(0, $this->storage->count());
    }

    public function testFailedSaveRemovesStoredBytes(): void
    {
        $handler = new UploadFileHandler(new InMemoryFileRepository(failOnSave: true), $this->storage);

        try {
            $handler(new UploadFileCommand('схема.png', 'image/png', 'байты', 'public'));
            self::fail('ожидался сбой записи');
        } catch (\RuntimeException) {
        }

        self::assertSame(0, $this->storage->count(), 'объект без записи в базе — сирота, его убираем');
    }

    public function testOwnerReadsPrivateFileAndStrangerDoesNot(): void
    {
        $id = $this->upload(visibility: 'private', owner: 'Аня')->id;

        $view = (new GetFileHandler($this->files, $this->storage))(new GetFileQuery($id, 'Аня'));
        self::assertSame($id, $view->id);

        $this->expectException(FileAccessDenied::class);
        (new GetFileHandler($this->files, $this->storage))(new GetFileQuery($id, 'Боря'));
    }

    public function testAnyoneReadsPublicFile(): void
    {
        $id = $this->upload(visibility: 'public', owner: 'Аня')->id;

        $content = (new DownloadFileHandler($this->files, $this->storage))(new DownloadFileQuery($id));

        self::assertSame('байты', $content->contents);
        self::assertSame('image/png', $content->mimeType);
        self::assertSame('схема.png', $content->name);
    }

    public function testDownloadOfPrivateFileRequiresOwner(): void
    {
        $id = $this->upload(visibility: 'private', owner: 'Аня')->id;

        $this->expectException(FileAccessDenied::class);
        (new DownloadFileHandler($this->files, $this->storage))(new DownloadFileQuery($id));
    }

    public function testOwnerDeletesFileWithItsBytes(): void
    {
        $view = $this->upload(visibility: 'public', owner: 'Аня');
        $key = new StorageKey(Visibility::Public, substr((string) $view->url, strlen('/files/')));
        self::assertTrue($this->storage->has($key));

        (new DeleteFileHandler($this->files, $this->storage))(new DeleteFileCommand($view->id, 'Аня'));

        self::assertSame(0, $this->files->count());
        self::assertFalse($this->storage->has($key));
    }

    public function testStrangerCannotDeleteEvenPublicFile(): void
    {
        $id = $this->upload(visibility: 'public', owner: 'Аня')->id;

        try {
            (new DeleteFileHandler($this->files, $this->storage))(new DeleteFileCommand($id, 'Боря'));
            self::fail('чужой файл удалять нельзя');
        } catch (FileAccessDenied) {
        }

        self::assertSame(1, $this->files->count());
        self::assertSame(1, $this->storage->count());
    }

    public function testMissingFileIsReportedAsNotFound(): void
    {
        $this->expectException(FileNotFound::class);

        (new GetFileHandler($this->files, $this->storage))(new GetFileQuery(FileId::generate()->toString()));
    }

    private function upload(
        string $name = 'схема.png',
        string $mimeType = 'image/png',
        string $contents = 'байты',
        string $visibility = 'public',
        ?string $owner = 'Аня',
    ): FileView {
        $handler = new UploadFileHandler($this->files, $this->storage);

        return $handler(new UploadFileCommand($name, $mimeType, $contents, $visibility, $owner));
    }
}
