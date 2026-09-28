<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\HealthController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class HealthControllerTest extends TestCase
{
    public function testHealthEndpointReturnsOkStatus(): void
    {
        $response = (new HealthController())();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($payload);
        $this->assertSame('ok', $payload['status']);
        $this->assertSame('penderie', $payload['app']);
    }
}
