<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * JSON failure response for POST /api/auth/login.
 */
final class JsonLoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return AuthResponse::error('too_many_attempts', 'Too many login attempts. Please wait a minute and try again.', Response::HTTP_TOO_MANY_REQUESTS);
        }

        return AuthResponse::error('invalid_credentials', 'Invalid e-mail or password.', Response::HTTP_UNAUTHORIZED);
    }
}
