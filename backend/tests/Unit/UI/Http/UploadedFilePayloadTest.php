<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http;

use App\Domain\Shared\InvalidArgument;
use App\UI\Http\UploadedFilePayload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(UploadedFilePayload::class)]
final class UploadedFilePayloadTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }
    }

    public function testMimeTypeComesFromContentNotFromClient(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true);
        self::assertNotFalse($png);

        $payload = UploadedFilePayload::of($this->requestWith($png, 'схема.txt', 'text/plain'));

        self::assertSame('схема.txt', $payload->name);
        self::assertSame('image/png', $payload->mimeType, 'клиент сказал text/plain, но байты — PNG');
        self::assertSame($png, $payload->contents);
    }

    public function testMissingFileIsDomainError(): void
    {
        try {
            UploadedFilePayload::of(new Request());
            self::fail('без файла разбор невозможен');
        } catch (InvalidArgument $e) {
            self::assertSame('file', $e->field());
        }
    }

    public function testRejectedByPhpUploadIsDomainError(): void
    {
        $request = new Request(files: ['file' => new UploadedFile(
            $this->tempFile(''),
            'big.png',
            error: UPLOAD_ERR_INI_SIZE,
            test: true,
        )]);

        try {
            UploadedFilePayload::of($request);
            self::fail('отброшенный PHP файл принимать нельзя');
        } catch (InvalidArgument $e) {
            self::assertSame('file', $e->field());
            self::assertStringContainsString('размера', $e->getMessage());
        }
    }

    private function requestWith(string $contents, string $clientName, string $clientMimeType): Request
    {
        return new Request(files: ['file' => new UploadedFile(
            $this->tempFile($contents),
            $clientName,
            $clientMimeType,
            test: true,
        )]);
    }

    private function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        self::assertNotFalse($path);
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }
}
