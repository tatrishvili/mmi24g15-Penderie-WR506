<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\ValidationFailedHttpException;
use App\Security\AuthResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Converts every exception raised under /api into a JSON error payload,
 * so the SPA never receives an HTML error page.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, method: 'onKernelException')]
final class ApiExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $throwable = $event->getThrowable();

        if ($throwable instanceof ValidationFailedHttpException) {
            $event->setResponse(AuthResponse::error(
                'validation_failed',
                $throwable->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $throwable->getViolations(),
            ));

            return;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
            $message = $throwable->getMessage() !== '' ? $throwable->getMessage() : (Response::$statusTexts[$status] ?? 'Error');

            $event->setResponse(AuthResponse::error($this->codeFor($status), $message, $status));

            return;
        }

        $event->setResponse(AuthResponse::error('internal_error', 'An unexpected error occurred.', Response::HTTP_INTERNAL_SERVER_ERROR));
    }

    private function codeFor(int $status): string
    {
        return match ($status) {
            Response::HTTP_BAD_REQUEST => 'bad_request',
            Response::HTTP_UNAUTHORIZED => 'unauthenticated',
            Response::HTTP_FORBIDDEN => 'forbidden',
            Response::HTTP_NOT_FOUND => 'not_found',
            Response::HTTP_CONFLICT => 'conflict',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'validation_failed',
            Response::HTTP_TOO_MANY_REQUESTS => 'too_many_attempts',
            default => 'error',
        };
    }
}
