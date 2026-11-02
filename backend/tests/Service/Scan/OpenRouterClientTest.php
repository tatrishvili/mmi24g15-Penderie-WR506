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

    public function testTransportFailureBecomesABadGateway(): void
    {
        $client = new OpenRouterClient(
            'test-api-key',
            'google/gemma-4-31b-it:free',
            new MockHttpClient(new MockResponse('', ['error' => 'network down'])),
        );

        try {
            $client->analyzeImage('aGVsbG8=');
            self::fail('Expected the transport failure to be reported.');
        } catch (HttpException $exception) {
            self::assertSame(502, $exception->getStatusCode());
            self::assertStringContainsString('failed', $exception->getMessage());
        }
    }

    public function testResponseWithoutMessageContentIsRejected(): void
    {
        $client = new OpenRouterClient(
            'test-api-key',
            'google/gemma-4-31b-it:free',
            new MockHttpClient([
                new MockResponse('{"choices":[{"message":{}}]}', ['http_code' => 200]),
            ]),
        );

        try {
            $client->analyzeImage('aGVsbG8=');
            self::fail('Expected a missing message content to be reported.');
        } catch (HttpException $exception) {
            self::assertSame(502, $exception->getStatusCode());
            self::assertStringContainsString('invalid data', $exception->getMessage());
        }
    }

    public function testSuccessfulAnalysisNormalizesEveryItemField(): void
    {
        $content = json_encode([
            'choices' => [
                ['message' => ['content' => json_encode([
                    'name' => 'Pull noir',
                    'type' => 'pull',
                    'category' => 'haut',
                    'brand' => 'Zara',
                    'color' => 'noir',
                    'size' => 'M',
                    'material' => 'laine',
                    'condition' => 'bon',
                    'confidence' => 0.9,
                ])]],
            ],
        ]);

        $client = new OpenRouterClient(
            'test-api-key',
            'google/gemma-4-31b-it:free',
            new MockHttpClient([new MockResponse($content, ['http_code' => 200])]),
        );

        $item = $client->analyzeImage('aGVsbG8=');

        self::assertSame('Pull noir', $item['name']);
        self::assertSame('pull', $item['type']);
        self::assertSame('haut', $item['category']);
        self::assertSame('Zara', $item['brand']);
        self::assertSame('noir', $item['color']);
        self::assertSame('M', $item['size']);
        self::assertSame('laine', $item['material']);
        self::assertSame('bon', $item['condition']);
        self::assertSame(0.9, $item['confidence']);
    }

    public function testUnknownTypeFallsBackAndDataUrlPrefixesAreStripped(): void
    {
        $content = json_encode([
            'choices' => [
                ['message' => ['content' => json_encode([
                    'name' => 'Chaussettes',
                    'type' => 'gilet inconnu',
                ])]],
            ],
        ]);

        $client = new OpenRouterClient(
            'test-api-key',
            'google/gemma-4-31b-it:free',
            new MockHttpClient([new MockResponse($content, ['http_code' => 200])]),
        );

        $item = $client->analyzeImage('data:image/jpeg;base64,aGVsbG8=');

        self::assertSame('Chaussettes', $item['name']);
        self::assertSame('autre', $item['type'], 'Unknown types must fall back to "autre".');
        self::assertSame('', $item['brand']);
        self::assertSame('abime', $item['condition']);
        self::assertSame(0.0, $item['confidence']);
    }
}
