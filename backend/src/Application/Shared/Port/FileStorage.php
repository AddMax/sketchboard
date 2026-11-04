<?php

declare(strict_types=1);

namespace App\Application\Shared\Port;

use App\Domain\File\ValueObject\MimeType;
use App\Domain\File\ValueObject\StorageKey;

/**
 * Порт хранилища байтов. Домен решает, под каким ключом и в каком бакете
 * лежит файл; что за этим стоит — S3, локальный каталог или память
 * в тесте — его не касается.
 *
 * Содержимое ходит строкой: предел размера файла (FileSize::MAX_BYTES)
 * много меньше memory_limit, а потоки усложнили бы и порт, и тесты.
 */
interface FileStorage
{
    /**
     * @throws StorageUnavailable
     */
    public function store(StorageKey $key, string $contents, MimeType $mimeType): void;

    /**
     * @throws StorageUnavailable
     */
    public function read(StorageKey $key): string;

    /**
     * Удаление отсутствующего объекта — не ошибка.
     *
     * @throws StorageUnavailable
     */
    public function remove(StorageKey $key): void;

    /**
     * Ссылка, по которой публичный файл читается без бэкенда.
     * Для приватного ключа такой ссылки нет.
     */
    public function publicUrl(StorageKey $key): ?string;
}
