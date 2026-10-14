<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Household;
use App\Entity\Place;
use App\Entity\Profile;
use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single place that builds every auth JSON payload, so the API shape stays
 * consistent across login, register, PIN login, /me and profile switching.
 */
final class AuthResponse
{
    /**
     * @param array<string, string> $violations
     */
    public static function error(string $code, string $message, int $status, array $violations = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ([] !== $violations) {
            $error['violations'] = $violations;
        }

        return new JsonResponse(['error' => $error], $status);
    }

    /**
     * @return array<string, mixed>
     */
    public static function userPayload(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'username' => $user->getUsername(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function householdPayload(Household $household): array
    {
        return ['id' => $household->getId(), 'name' => $household->getName(), 'slug' => $household->getSlug()];
    }

    /**
     * @return array<string, mixed>
     */
    public static function profilePayload(Profile $profile): array
    {
        return [
            'id' => $profile->getId(),
            'name' => $profile->getName(),
            'role' => $profile->getRole()->value,
            'avatarColor' => $profile->getAvatarColor(),
            'hasPin' => null !== $profile->getPinHash(),
            'householdId' => $profile->getHousehold()->getId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function placePayload(Place $place): array
    {
        return ['id' => $place->getId(), 'name' => $place->getName(), 'isDefault' => $place->isDefault()];
    }

    public static function validationFailed(\Symfony\Component\Validator\ConstraintViolationListInterface $violations): JsonResponse
    {
        $mapped = [];

        foreach ($violations as $violation) {
            $field = (string) $violation->getPropertyPath();
            $mapped[$field] = (string) $violation->getMessage();
        }

        return self::error('validation_failed', 'Some fields are invalid.', Response::HTTP_UNPROCESSABLE_ENTITY, $mapped);
    }
}
