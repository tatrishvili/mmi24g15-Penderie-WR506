<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ChildAccount;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: 'kernel.request', priority: -10)]
final class ChildPasswordRequirement
{
    public function __construct(private TokenStorageInterface $tokens)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $user = $this->tokens->getToken()?->getUser();
        if ($user instanceof ChildAccount && $user->mustChangePassword && !\in_array($event->getRequest()->getPathInfo(), ['/api/auth/me', '/api/auth/child-password', '/api/auth/logout', '/api/auth/login', '/api/auth/child-login'], true)) {
            $event->setResponse(AuthResponse::error('password_change_required', 'Choose your own password before continuing.', 403));
        }
    }
}
