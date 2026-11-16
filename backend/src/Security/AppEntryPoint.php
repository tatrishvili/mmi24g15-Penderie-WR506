<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * API callers get a JSON 401; browser requests to the Twig back office are sent
 * to the SPA login page with a return path.
 */
final class AppEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly ApiEntryPoint $api,
        #[Autowire('%env(default:frontend_app_url_default:PUBLIC_APP_URL)%')]
        private readonly string $frontendBaseUrl,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (!str_starts_with($request->getPathInfo(), '/admin')) {
            return $this->api->start($request, $authException);
        }

        $next = $request->getPathInfo();
        $query = $request->getQueryString();
        if (null !== $query && '' !== $query) {
            $next .= '?'.$query;
        }

        return new RedirectResponse(rtrim($this->frontendBaseUrl, '/').'/connexion?next='.rawurlencode($next));
    }
}
