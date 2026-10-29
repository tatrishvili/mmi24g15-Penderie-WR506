<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\CreateHouseholdDto;
use App\Dto\CreateProfileDto;
use App\Dto\RegisterDto;
use App\Entity\Profile;
use App\Security\AuthResponse;
use App\Service\AuthService;
use App\Service\CurrentActor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private AuthService $auth,
        private CurrentActor $actor,
        private EntityManagerInterface $em,
        #[Autowire(service: 'limiter.pin')]
        private RateLimiterFactory $pinLimiter,
        #[Autowire(service: 'limiter.registration')]
        private RateLimiterFactory $registrationLimiter,
        #[Autowire(service: 'limiter.verification_resend')]
        private RateLimiterFactory $verificationResendLimiter,
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $this->enforceLimit($this->registrationLimiter, $request);
        $this->auth->register(RegisterDto::fromArray($this->jsonBody($request)));

        return new JsonResponse([
            'message' => 'If the address can be registered, a verification email will be sent.',
        ], Response::HTTP_ACCEPTED);
    }

    #[Route('/verify-email', name: 'api_auth_verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $payload = $this->auth->verifyEmail(
            $this->stringField($this->jsonBody($request), 'token'),
            $request,
        );

        return new JsonResponse($payload);
    }

    #[Route('/resend-verification', name: 'api_auth_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): JsonResponse
    {
        $this->enforceLimit($this->verificationResendLimiter, $request);
        $this->auth->resendVerification($this->stringField($this->jsonBody($request), 'email'));

        return new JsonResponse([
            'message' => 'If a pending registration exists for that address, a verification email will be sent.',
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Intercepted by the firewall (json_login authenticator): this body never runs.
     * The route MUST exist because the RouterListener (priority 32) runs before
     * the firewall (priority 8) — without it, POST /login ends in a 404.
     */
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        throw new \LogicException('Intercepted by the firewall.');
    }

    #[Route('/profile-pin', name: 'api_auth_profile_pin', methods: ['POST'])]
    public function profilePin(Request $request): JsonResponse
    {
        $user = $this->actor->requireUser();
        $household = $this->actor->requireHousehold();
        $data = $this->jsonBody($request);
        $profileId = isset($data['profileId']) && \is_int($data['profileId']) ? $data['profileId'] : 0;
        $pin = $this->stringField($data, 'pin');

        if ($profileId < 1 || 1 !== preg_match('/^\d{4,6}$/', $pin)) {
            throw new UnauthorizedHttpException('', 'Invalid profile or PIN.');
        }

        $limiterKey = implode(':', [$user->getId(), $profileId, (string) $request->getClientIp()]);
        if (false === $this->pinLimiter->create($limiterKey)->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException('Too many PIN attempts. Please wait a minute and try again.');
        }

        $profile = $this->em->getRepository(Profile::class)->findOneBy([
            'id' => $profileId,
            'household' => $household,
        ]);

        if (!$profile instanceof Profile || null === $profile->getPinHash() || !$this->hasher->isPasswordValid($profile, $pin)) {
            throw new UnauthorizedHttpException('', 'Invalid profile or PIN.');
        }

        $request->getSession()->set('penderie.active_profile_id', $profile->getId());

        return new JsonResponse([
            'profile' => AuthResponse::profilePayload($profile),
            'activeProfileId' => $profile->getId(),
        ]);
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return new JsonResponse($this->auth->mePayload($this->actor));
    }

    #[Route('/username', name: 'api_auth_username_set', methods: ['PUT'])]
    public function setUsername(Request $request): JsonResponse
    {
        $username = $this->auth->setUsername(
            $this->actor,
            $this->stringField($this->jsonBody($request), 'username'),
        );

        return new JsonResponse(['username' => $username]);
    }

    #[Route('/households', name: 'api_auth_households_create', methods: ['POST'])]
    public function createHousehold(Request $request): JsonResponse
    {
        $payload = $this->auth->createHousehold($this->actor, CreateHouseholdDto::fromArray($this->jsonBody($request)), $request);

        return new JsonResponse($payload, Response::HTTP_CREATED);
    }

    #[Route('/households/{id}/select', name: 'api_auth_households_select', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function selectHousehold(int $id, Request $request): JsonResponse
    {
        return new JsonResponse($this->auth->selectHousehold($this->actor, $id, $request));
    }

    #[Route('/profiles', name: 'api_auth_profiles_create', methods: ['POST'])]
    public function createProfile(Request $request): JsonResponse
    {
        $dto = CreateProfileDto::fromArray($this->jsonBody($request));

        $payload = $this->auth->createProfile($this->actor, $dto);

        return new JsonResponse($payload, Response::HTTP_CREATED);
    }

    #[Route('/profiles/{id}/switch', name: 'api_auth_profiles_switch', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function switchProfile(int $id, Request $request): JsonResponse
    {
        $profile = $this->auth->switchProfile($this->actor, $id, $request);

        return new JsonResponse([
            'profile' => AuthResponse::profilePayload($profile),
            'activeProfileId' => $profile->getId(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(Request $request): array
    {
        try {
            $data = $request->toArray();
        } catch (JsonException) {
            throw new UnprocessableEntityHttpException('Request body must be valid JSON.');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function stringField(array $payload, string $field): string
    {
        $value = $payload[$field] ?? null;

        return \is_string($value) ? $value : '';
    }

    private function enforceLimit(RateLimiterFactory $factory, Request $request): void
    {
        if (false === $factory->create((string) $request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException('Too many requests. Please wait and try again.');
        }
    }
}
