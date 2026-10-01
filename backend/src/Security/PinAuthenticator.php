<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Household;
use App\Entity\Profile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Standalone child login (Mode C): household slug -> profile -> PIN.
 *
 * Anti-enumeration: slug, profile and PIN failures all return the exact same
 * error, so an attacker cannot probe which households or profiles exist.
 */
final class PinAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        #[Autowire(service: 'limiter.pin')]
        private RateLimiterFactory $pinLimiter,
    ) {
    }

    public function supports(Request $request): bool
    {
        return 'POST' === $request->getMethod() && '/api/auth/profile-pin' === $request->getPathInfo();
    }

    public function authenticate(Request $request): Passport
    {
        $limiter = $this->pinLimiter->create((string) $request->getClientIp());

        if (false === $limiter->consume()->isAccepted()) {
            throw new TooManyLoginAttemptsAuthenticationException();
        }

        try {
            /** @var array<string, mixed> $data */
            $data = $request->toArray();
        } catch (JsonException) {
            throw new CustomUserMessageAuthenticationException('Invalid household, profile or PIN.');
        }

        $slug = \is_string($data['householdSlug'] ?? null) ? trim((string) $data['householdSlug']) : '';
        $profileId = isset($data['profileId']) ? (int) $data['profileId'] : 0;
        $pin = \is_string($data['pin'] ?? null) ? (string) $data['pin'] : '';

        if ('' === $slug || $profileId <= 0 || '' === $pin) {
            throw new CustomUserMessageAuthenticationException('Invalid household, profile or PIN.');
        }

        $household = $this->em->getRepository(Household::class)->findOneBy(['slug' => $slug]);

        $profile = null;

        if (null !== $household) {
            $profile = $this->em->getRepository(Profile::class)->findOneBy(['id' => $profileId, 'household' => $household]);
        }

        if (!$profile instanceof Profile || null === $profile->getPinHash() || !$this->hasher->isPasswordValid($profile, $pin)) {
            throw new CustomUserMessageAuthenticationException('Invalid household, profile or PIN.');
        }

        return new SelfValidatingPassport(new UserBadge($profile->getUserIdentifier(), fn (): Profile => $profile));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        $profile = $token->getUser();
        \assert($profile instanceof Profile);

        $request->getSession()->set('penderie.active_profile_id', $profile->getId());

        return new JsonResponse([
            'profile' => AuthResponse::profilePayload($profile),
            'household' => AuthResponse::householdPayload($profile->getHousehold()),
            'activeProfileId' => $profile->getId(),
        ]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return AuthResponse::error('too_many_attempts', 'Too many PIN attempts. Please wait a minute and try again.', Response::HTTP_TOO_MANY_REQUESTS);
        }

        return AuthResponse::error('invalid_pin', 'Invalid household, profile or PIN.', Response::HTTP_UNAUTHORIZED);
    }
}
