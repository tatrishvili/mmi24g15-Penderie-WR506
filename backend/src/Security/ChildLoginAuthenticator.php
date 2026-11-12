<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ChildAccount;
use App\Service\AuthService;
use App\Service\CurrentActor;
use App\Service\FamilyAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final class ChildLoginAuthenticator extends AbstractAuthenticator
{
    public function __construct(private EntityManagerInterface $em, private FamilyAccess $access, private AuthService $auth, private CurrentActor $actor,
        #[Autowire(service: 'limiter.child_login_account')] private RateLimiterFactory $accounts,
        #[Autowire(service: 'limiter.child_login_ip')] private RateLimiterFactory $ips,
    ) {
    }

    public function supports(Request $request): bool
    {
        return '/api/auth/child-login' === $request->getPathInfo() && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            throw new BadCredentialsException();
        }
        $username = \is_string($data['username'] ?? null) ? strtolower(trim($data['username'])) : '';
        $password = \is_string($data['password'] ?? null) ? $data['password'] : '';
        $accountLimit = $this->accounts->create(hash('sha256', $username))->consume()->isAccepted();
        $ipLimit = $this->ips->create((string) $request->getClientIp())->consume()->isAccepted();
        if (!$accountLimit || !$ipLimit) {
            throw new TooManyRequestsHttpException(60, 'Please wait a minute before trying again.');
        }

        return new Passport(new UserBadge('child:'.$username, function () use ($username): ChildAccount {
            $account = $this->em->getRepository(ChildAccount::class)->findOneBy(['username' => $username]);
            if (!$account instanceof ChildAccount || !$this->access->childEnabled($account)
                || ($account->mustChangePassword && null !== $account->initialPasswordExpiresAt && $account->initialPasswordExpiresAt < new \DateTimeImmutable())) {
                throw new BadCredentialsException();
            }

            return $account;
        }), new PasswordCredentials($password));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        $request->getSession()->remove('penderie.active_household_id');
        $request->getSession()->remove('penderie.active_profile_id');

        return new JsonResponse($this->auth->mePayload($this->actor));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return AuthResponse::error('invalid_credentials', 'Invalid username or password.', 401);
    }
}
