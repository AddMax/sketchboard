<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\File;

use App\Domain\File\ValueObject\FileId;
use App\Domain\File\ValueObject\FileSize;
use App\Domain\File\ValueObject\MimeType;
use App\Domain\File\ValueObject\OriginalName;
use App\Domain\File\ValueObject\Visibility;
use App\Domain\Shared\InvalidArgument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileId::class)]
#[CoversClass(FileSize::class)]
#[CoversClass(MimeType::class)]
#[CoversClass(OriginalName::class)]
#[CoversClass(Visibility::class)]
final class ValueObjectTest extends TestCase
{
    public function testMimeTypeIsNormalized(): void
    {
        $mime = MimeType::fromString(' Image/PNG ; charset=binary');

        self::assertSame('image/png', $mime->toString());
        self::assertSame('png', $mime->extension());
    }

    #[DataProvider('rejectedMimeTypeProvider')]
    public function testUnknownMimeTypeIsRejected(string $value): void
    {
        $this->expectException(InvalidArgument::class);

        try {
            MimeType::fromString($value);
        } catch (InvalidArgument $e) {
            self::assertSame('mimeType', $e->field());

            throw $e;
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedMimeTypeProvider(): iterable
    {
        yield 'исполняемый файл' => ['application/x-msdownload'];
        yield 'html — исполняется в браузере' => ['text/html'];
        yield 'пустая строка' => [''];
        yield 'только параметры' => ['; charset=utf-8'];
    }

    public function testSizeLimits(): void
    {
        self::assertSame(1, FileSize::ofBytes(1)->bytes());
        self::assertSame(FileSize::MAX_BYTES, FileSize::ofBytes(FileSize::MAX_BYTES)->bytes());
    }

    #[DataProvider('invalidSizeProvider')]
    public function testEmptyOrOversizedFileIsRejected(int $bytes): void
    {
        $this->expectException(InvalidArgument::class);

        try {
            FileSize::ofBytes($bytes);
        } catch (InvalidArgument $e) {
            self::assertSame('file', $e->field());

            throw $e;
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidSizeProvider(): iterable
    {
        yield 'пустой' => [0];
        yield 'отрицательный' => [-1];
        yield 'на байт больше предела' => [FileSize::MAX_BYTES + 1];
    }

    #[DataProvider('nameProvider')]
    public function testNameKeepsOnlyBasename(string $given, string $expected): void
    {
        self::assertSame($expected, OriginalName::fromString($given)->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function nameProvider(): iterable
    {
        yield 'простое имя' => ['схема.png', 'схема.png'];
        yield 'путь unix' => ['/home/user/схема.png', 'схема.png'];
        yield 'путь windows' => ['C:\\Users\\user\\схема.png', 'схема.png'];
        yield 'пробелы по краям' => ["  схема.png \n", 'схема.png'];
    }

    #[DataProvider('invalidNameProvider')]
    public function testMalformedNameIsRejected(string $value): void
    {
        $this->expectException(InvalidArgument::class);

        try {
            OriginalName::fromString($value);
        } catch (InvalidArgument $e) {
            self::assertSame('name', $e->field());

            throw $e;
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNameProvider(): iterable
    {
        yield 'пустое' => [''];
        yield 'только каталог' => ['/tmp/'];
        yield 'ссылка на родителя' => ['..'];
        yield 'управляющий символ' => ["схема\x00.png"];
        yield 'длиннее предела' => [str_repeat('я', OriginalName::MAX_LENGTH + 1)];
    }

    public function testVisibilityIsParsedCaseInsensitively(): void
    {
        self::assertSame(Visibility::Public, Visibility::fromString(' PUBLIC '));
        self::assertSame(Visibility::Private, Visibility::fromString('private'));
        self::assertTrue(Visibility::Public->isPublic());
        self::assertFalse(Visibility::Private->isPublic());
    }

    public function testUnknownVisibilityIsRejected(): void
    {
        $this->expectException(InvalidArgument::class);

        try {
            Visibility::fromString('secret');
        } catch (InvalidArgument $e) {
            self::assertSame('visibility', $e->field());

            throw $e;
        }
    }

    public function testIdValidatesFormat(): void
    {
        $this->expectException(InvalidArgument::class);

        FileId::fromString('не-uuid');
    }

    public function testIdIsRestoredFromString(): void
    {
        $id = FileId::generate();

        self::assertTrue($id->equals(FileId::fromString($id->toString())));
    }
}
