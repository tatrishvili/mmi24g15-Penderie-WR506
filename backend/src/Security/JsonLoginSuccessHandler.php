<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\HouseholdMember;
use App\Entity\Place;
use App\Entity\Profile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * JSON success response for POST /api/auth/login.
 * A fresh login clears the session first, then restores the foyer
 * automatically when the user owns exactly one: re-logging in lands back
 * where they left off. With several foyers the frontend picks one via
 * POST /api/auth/households/{id}/select.
 */
final class JsonLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $session = $request->getSession();
        $session->remove('penderie.active_household_id');
        $session->remove('penderie.active_profile_id');
        $session->remove('penderie.child_session_version');

        $user = $token->getUser();
        \assert($user instanceof User);

        $members = $this->em->getRepository(HouseholdMember::class)->findBy(['user' => $user], ['id' => 'ASC']);
        $households = array_map(
            static fn (HouseholdMember $member): array => AuthResponse::householdPayload($member->getHousehold()),
            $members,
        );

        $payload = [
            'user' => AuthResponse::userPayload($user),
            'households' => $households,
            'household' => null,
            'profiles' => [],
            'places' => [],
            'activeHouseholdId' => null,
            'activeProfileId' => null,
            'activeProfile' => null,
        ];

        if (1 !== \count($members)) {
            return new JsonResponse($payload);
        }

        $household = $members[0]->getHousehold();

        $ownAdult = $this->em->getRepository(Profile::class)->findOneBy(
            ['user' => $user, 'household' => $household],
            ['id' => 'ASC'],
        );

        if (!$ownAdult instanceof Profile || !$ownAdult->isAdult()) {
            return new JsonResponse($payload);
        }

        $session->set('penderie.active_household_id', $household->getId());
        $session->set('penderie.active_profile_id', $ownAdult->getId());

        $profiles = $this->em->getRepository(Profile::class)->findBy(['household' => $household], ['id' => 'ASC']);
        $places = $this->em->getRepository(Place::class)->findBy(['household' => $household], ['id' => 'ASC']);

        $payload['household'] = AuthResponse::householdPayload($household);
        $payload['profiles'] = array_map([AuthResponse::class, 'profilePayload'], $profiles);
        $payload['places'] = array_map([AuthResponse::class, 'placePayload'], $places);
        $payload['activeHouseholdId'] = $household->getId();
        $payload['activeProfileId'] = $ownAdult->getId();
        $payload['activeProfile'] = AuthResponse::profilePayload($ownAdult);

        return new JsonResponse($payload);
    }
}
