<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Answers POST /api/auth/logout with an empty 204 instead of a redirect.
 */
#[AsEventListener(event: LogoutEvent::class, method: 'onLogout')]
final class JsonLogoutListener
{
    public function onLogout(LogoutEvent $event): void
    {
        $event->setResponse(new Response(null, Response::HTTP_NO_CONTENT));
    }
}
