<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps the Twig back office in the language explicitly chosen in the header.
 * Runs before Symfony's LocaleListener so its _locale attribute wins over the
 * browser Accept-Language header.
 */
final class AdminLocaleSubscriber implements EventSubscriberInterface
{
    private const LOCALES = ['fr', 'en', 'ka'];

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 20]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/admin')) {
            return;
        }

        $session = $request->getSession();
        $lang = $request->query->get('lang');

        if (\is_string($lang) && \in_array($lang, self::LOCALES, true)) {
            $session->set('_locale', $lang);
        }

        $locale = $session->get('_locale');

        if (\is_string($locale) && \in_array($locale, self::LOCALES, true)) {
            $request->attributes->set('_locale', $locale);
        }
    }
}
