<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\CreateProfileDto;
use App\Dto\RegisterDto;
use App\Entity\Household;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private AuthService $auth,
        private CurrentActor $actor,
        private EntityManagerInterface $em,
        #[Autowire(service: 'limiter.household_profiles')]
        private RateLimiterFactory $profilesLimiter,
    ) {
    }

    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = $this->auth->register(RegisterDto::fromArray($this->jsonBody($request)));

        return new JsonResponse($payload, Response::HTTP_CREATED);
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

    /**
     * Intercepted by the firewall (PinAuthenticator): this body never runs.
     * Same routing reason as login() above.
     */
    #[Route('/profile-pin', name: 'api_auth_profile_pin', methods: ['POST'])]
    public function profilePin(): JsonResponse
    {
        throw new \LogicException('Intercepted by the firewall.');
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return new JsonResponse($this->auth->mePayload($this->actor));
    }

    /**
     * Public minimal profile listing for a household slug. Powers the standalone
     * PIN login UI (slug -> profile -> PIN) without leaking anything sensitive:
     * only id, name, role, avatar color and whether a PIN is set.
     * Rate-limited per IP to slow down slug enumeration.
     */
    #[Route('/households/{slug}/profiles', name: 'api_auth_household_profiles', methods: ['GET'])]
    public function householdProfiles(string $slug, Request $request): JsonResponse
    {
        $limiter = $this->profilesLimiter->create((string) $request->getClientIp());

        if (false === $limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException('Too many requests. Please wait a minute and try again.');
        }

        $household = $this->em->getRepository(Household::class)->findOneBy(['slug' => $slug]);

        if (!$household instanceof Household) {
            throw new NotFoundHttpException('Household not found.');
        }

        $profiles = $this->em->getRepository(Profile::class)->findBy(['household' => $household], ['id' => 'ASC']);

        return new JsonResponse([
            'household' => ['name' => $household->getName(), 'slug' => $household->getSlug()],
            'profiles' => array_map([AuthResponse::class, 'profilePayload'], $profiles),
        ]);
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
}
