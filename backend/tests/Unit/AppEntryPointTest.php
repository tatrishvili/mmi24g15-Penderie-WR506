<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Security\ApiEntryPoint;
use App\Security\AppEntryPoint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final class AppEntryPointTest extends TestCase
{
    public function testApiRequestsKeepTheJsonEntryPoint(): void
    {
        $response = (new AppEntryPoint(new ApiEntryPoint(), 'http://localhost:5173'))->start(Request::create('/api/items'));

        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }

    public function testAdminRedirectHasNoEmptyQueryString(): void
    {
        $response = (new AppEntryPoint(new ApiEntryPoint(), 'http://localhost:5173'))->start(Request::create('/admin'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('http://localhost:5173/connexion?next=%2Fadmin', $response->getTargetUrl());
    }

    public function testAdminRequestsRedirectToTheSpaLogin(): void
    {
        $response = (new AppEntryPoint(new ApiEntryPoint(), 'https://app.example.test'))->start(
            Request::create('/admin/journal?page=2'),
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(
            'https://app.example.test/connexion?next=%2Fadmin%2Fjournal%3Fpage%3D2',
            $response->getTargetUrl(),
        );
    }
}
