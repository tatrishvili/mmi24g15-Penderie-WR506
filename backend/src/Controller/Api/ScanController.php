<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Scan\OpenRouterClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ScanController extends AbstractController
{
    public function __construct(
        private OpenRouterClient $openRouterClient,
        private \App\Service\CurrentActor $actor,
    ) {
    }

    #[Route('/api/scan', name: 'api_scan', methods: ['POST'])]
    public function scan(Request $request): JsonResponse
    {
        $this->actor->requireHousehold();
        $data = $request->toArray();

        if (!isset($data['image']) || !is_string($data['image'])) {
            throw new UnprocessableEntityHttpException('Missing or invalid "image" parameter.');
        }

        $imageBase64 = $data['image'];

        if ('' === trim($imageBase64)) {
            throw new UnprocessableEntityHttpException('Missing or invalid "image" parameter.');
        }

        if (strlen($imageBase64) > 10_000_000) {
            throw new UnprocessableEntityHttpException('Image too large (max ~10MB).');
        }

        $typeHint = $data['typeHint'] ?? null;

        if (null !== $typeHint && (!\is_string($typeHint) || !\in_array($typeHint, ['tshirt', 'pantalon', 'jupe', 'autre'], true))) {
            throw new UnprocessableEntityHttpException('Invalid item type hint.');
        }

        $result = $this->openRouterClient->analyzeImage($imageBase64, $typeHint);

        return new JsonResponse($result, Response::HTTP_OK);
    }
}
