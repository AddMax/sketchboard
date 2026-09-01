<?php

declare(strict_types=1);

namespace App\UI\Http\EventListener;

use App\Domain\Note\Exception\NoteNotFound;
use App\Domain\Shared\InvalidArgument;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Переводит язык домена в коды HTTP в одном месте — контроллерам не нужно
 * оборачивать каждый вызов в try/catch.
 */
#[AsEventListener(event: ExceptionEvent::class)]
final readonly class DomainExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();

        $response = match (true) {
            $exception instanceof NoteNotFound => new JsonResponse(
                ['error' => $exception->getMessage()],
                Response::HTTP_NOT_FOUND,
            ),
            $exception instanceof InvalidArgument => new JsonResponse(
                ['errors' => [($exception->field() ?: 'request') => $exception->getMessage()]],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ),
            default => null,
        };

        if (null !== $response) {
            $event->setResponse($response);
        }
    }
}
