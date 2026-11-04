<?php

declare(strict_types=1);

namespace App\Application\Shared\Port;

/**
 * Хранилище байтов не ответило или отказало. Это не ошибка пользователя
 * и не нарушение инварианта — запрос падает с 500, детали в логе.
 */
class StorageUnavailable extends \RuntimeException
{
}
