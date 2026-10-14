<?php

declare(strict_types=1);

namespace App\Tests\Service\Scan;

use App\Service\Scan\OpenRouterClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class OpenRouterClientTest extends TestCase
{
    public function testRateLimitedProviderResponseBecomesRetryableServiceUnavailable(): void
    {
        $client = new OpenRouterClient(
            'test-api-key',
            'google/gemma-4-31b-it:free',
            new MockHttpClient([new MockResponse('rate limited', ['http_code' => 429])]),
        );

        try {
            $client->analyzeImage('aGVsbG8=', 'jupe');
            self::fail('Expected a rate-limited provider response to be reported.');
        } catch (HttpException $exception) {
            self::assertSame(503, $exception->getStatusCode());
            self::assertStringContainsString('temporarily rate-limited', $exception->getMessage());
        }
    }

    public function testInvalidProviderOutputDoesNotBecomeAnEmptySuccess(): void
    {
        $client = new OpenRouterClient(
            'test-api-key',
            'google/gemma-4-31b-it:free',
            new MockHttpClient([
                new MockResponse(
                    '{"choices":[{"message":{"content":"not JSON"}}]}',
                    ['http_code' => 200],
                ),
            ]),
        );

        try {
            $client->analyzeImage('aGVsbG8=');
            self::fail('Expected invalid provider output to be reported.');
        } catch (HttpException $exception) {
            self::assertSame(502, $exception->getStatusCode());
        }
    }
}
