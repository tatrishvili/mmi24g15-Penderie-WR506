<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Profile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * JSON success response for POST /api/auth/login.
 * A fresh login never carries an active profile: the frontend goes to /profils next.
 */
final class JsonLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $request->getSession()->remove('penderie.active_profile_id');

        $user = $token->getUser();
        \assert($user instanceof User);

        $household = $user->getHousehold();

        $profiles = $this->em->getRepository(Profile::class)->findBy(['household' => $household], ['id' => 'ASC']);

        return new \Symfony\Component\HttpFoundation\JsonResponse([
            'user' => AuthResponse::userPayload($user),
            'household' => AuthResponse::householdPayload($household),
            'profiles' => array_map([AuthResponse::class, 'profilePayload'], $profiles),
            'activeProfileId' => null,
        ]);
    }
}
