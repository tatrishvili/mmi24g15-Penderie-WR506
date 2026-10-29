<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ScanControllerTest extends ApiTestCase
{
    public function testScanRequiresAuthentication(): void
    {
        $client = static::createClient();

        $response = self::api($client, 'POST', '/api/scan', ['image' => 'aGVsbG8=']);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testScanRejectsMissingImage(): void
    {
        $client = static::createClient();
        self::registerAccount($client);

        $response = self::api($client, 'POST', '/api/scan', []);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testScanRejectsAnUnsupportedTypeHint(): void
    {
        $client = static::createClient();
        self::registerAccount($client);

        $response = self::api($client, 'POST', '/api/scan', [
            'image' => 'aGVsbG8=',
            'typeHint' => 'not-a-type',
        ]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testScanWithoutApiKeyReportsThatAnalysisIsUnavailable(): void
    {
        $client = static::createClient();
        self::registerAccount($client);

        $response = self::api($client, 'POST', '/api/scan', ['image' => 'aGVsbG8=']);

        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $this->assertSame(
            'Photo analysis is not configured. You can enter the item manually.',
            self::decoded($response)['error']['message'],
        );
    }
}
