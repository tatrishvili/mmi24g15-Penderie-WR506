<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\PageVisit;
use App\Service\CurrentActor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/api/analytics', name: 'api_analytics_')]
final class AnalyticsController
{
    private const LOCALES = ['fr', 'en', 'ka'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CurrentActor $actor,
        #[Autowire(service: 'limiter.analytics_visit')] private readonly RateLimiterFactory $visits,
    ) {
    }

    #[Route('/visit', name: 'visit', methods: ['POST'])]
    public function visit(Request $request): Response
    {
        if (!$this->visits->create((string) $request->getClientIp())->consume()->isAccepted()) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $path = \is_string($data['path'] ?? null) ? mb_substr($data['path'], 0, 255) : '/';
        $visitorId = \is_string($data['visitorId'] ?? null) && preg_match('/^[A-Za-z0-9_-]{8,64}$/', $data['visitorId'])
            ? $data['visitorId']
            : null;
        $locale = \is_string($data['locale'] ?? null) && \in_array($data['locale'], self::LOCALES, true)
            ? $data['locale']
            : null;

        $actor = $this->actor->tokenUser();
        $household = null;
        if (null !== $actor) {
            try {
                $household = $this->actor->household();
            } catch (\Throwable) {
                $household = null;
            }
        }

        if (null !== $visitorId) {
            $visitorId = hash('sha256', $visitorId);
        }

        $this->em->persist(new PageVisit($this->page($path), $visitorId, $household, $locale, null !== $actor));
        $this->em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function page(string $path): string
    {
        return match (true) {
            '/' === $path => 'home',
            str_starts_with($path, '/dressing'), str_starts_with($path, '/articles') => 'items',
            str_starts_with($path, '/scanner') => 'scan',
            str_starts_with($path, '/lieux') => 'places',
            str_starts_with($path, '/foyer/pieces') => 'places',
            str_starts_with($path, '/foyer/rangements') => 'storage',
            str_starts_with($path, '/foyer/enfants'), str_starts_with($path, '/enfant') => 'child',
            str_starts_with($path, '/foyer') => 'household',
            str_starts_with($path, '/profils') => 'profiles',
            str_starts_with($path, '/profil') => 'profile',
            str_starts_with($path, '/amis') => 'friends',
            str_starts_with($path, '/notifications') => 'notifications',
            str_starts_with($path, '/partages-famille') => 'family-shares',
            str_starts_with($path, '/partage') => 'share',
            str_starts_with($path, '/collections') => 'collections',
            str_starts_with($path, '/communaute') => 'community',
            str_starts_with($path, '/actualites') => 'feed',
            str_starts_with($path, '/prets') => 'loans',
            str_starts_with($path, '/emprunter') => 'loan-request',
            str_starts_with($path, '/acces') => 'access',
            str_starts_with($path, '/compte') => 'account',
            str_starts_with($path, '/vitrine') => 'showcase',
            str_starts_with($path, '/verifier-email') => 'verify',
            str_starts_with($path, '/onboarding') => 'onboarding',
            str_starts_with($path, '/connexion') => 'login',
            str_starts_with($path, '/inscription') => 'register',
            str_starts_with($path, '/admin') => 'admin',
            default => 'other',
        };
    }
}
