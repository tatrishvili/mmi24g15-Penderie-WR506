<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\ActivityLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Records successful API mutations without reading request bodies.
 * Paths are sanitised so shared tokens never reach the audit table.
 */
final class ActivityAuditSubscriber implements EventSubscriberInterface
{
    private const MUTATING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private readonly ActivityLogger $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        if (!str_starts_with($path, '/api')
            || str_starts_with($path, '/api/analytics')
            || str_starts_with($path, '/api/health')
            || !\in_array($method, self::MUTATING, true)
            || $event->getResponse()->getStatusCode() >= 400
        ) {
            return;
        }

        [$action, $subjectType, $subjectId] = $this->describe($request->attributes->get('_route'), $path, $method);
        $metadata = [
            'method' => $method,
            'path' => $this->sanitize($path),
            'route' => $request->attributes->get('_route'),
            'status' => $event->getResponse()->getStatusCode(),
        ];
        $changes = $request->attributes->get('activityChanges');
        if (\is_array($changes)) {
            $metadata['changes'] = array_values(array_intersect(
                ['name', 'type', 'category', 'room', 'storageLocation', 'author', 'artist', 'publisher', 'brand', 'color', 'size', 'material', 'condition', 'status', 'memory', 'notes'],
                $changes,
            ));
        }
        $object = $request->attributes->get('activityObject');
        if (\is_array($object)) {
            foreach (['objectName', 'ownerProfileId'] as $key) {
                if (isset($object[$key]) && (\is_string($object[$key]) || \is_int($object[$key]))) {
                    $metadata[$key] = $object[$key];
                }
            }
        }
        $responseContent = $event->getResponse()->getContent();
        if (false !== $responseContent && '' !== $responseContent) {
            $responseData = json_decode($responseContent, true);
            if (\is_array($responseData)) {
                foreach (['item', 'loan', 'household'] as $key) {
                    $responseObject = $responseData[$key] ?? null;
                    if (!\is_array($responseObject)) {
                        continue;
                    }
                    if (null === $subjectId && \is_int($responseObject['id'] ?? null)) {
                        $subjectId = $responseObject['id'];
                    }
                    $name = $responseObject['name'] ?? $responseObject['itemName'] ?? null;
                    if (\is_string($name)) {
                        $metadata['objectName'] = $name;
                    }
                    if (\is_array($responseObject['ownerProfile'] ?? null)
                        && \is_int($responseObject['ownerProfile']['id'] ?? null)) {
                        $metadata['ownerProfileId'] = $responseObject['ownerProfile']['id'];
                    }
                    break;
                }
            }
        }

        $this->logger->log($action, $subjectType, $subjectId, $metadata);
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?int}
     */
    private function describe(mixed $route, string $path, string $method): array
    {
        $verb = match ($method) {
            'POST' => 'created',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default => 'changed',
        };

        if (str_starts_with($path, '/api/auth/login')) {
            return ['auth.login', 'user', null];
        }
        if (str_starts_with($path, '/api/auth/logout')) {
            return ['auth.logout', 'user', null];
        }
        if (str_starts_with($path, '/api/auth/register')) {
            return ['auth.register', 'user', null];
        }
        if (str_starts_with($path, '/api/auth/verify-email')) {
            return ['auth.verified', 'user', null];
        }
        if (str_starts_with($path, '/api/auth/child-login')) {
            return ['auth.login', 'child', null];
        }
        if (str_starts_with($path, '/api/auth/households')) {
            if (str_ends_with($path, '/select')) {
                return ['household.selected', 'household', $this->id($path)];
            }

            return ['household.'.$verb, 'household', $this->id($path)];
        }
        if (str_starts_with($path, '/api/auth/profiles')) {
            return ['profile.'.$verb, 'profile', $this->id($path)];
        }
        if (str_starts_with($path, '/api/scan')) {
            return ['scan.created', 'scan', null];
        }
        if (str_contains($path, '/photos')) {
            return ['item.photo.'.$verb, 'item', $this->id($path)];
        }
        if (str_starts_with($path, '/api/items')) {
            return ['item.'.$verb, 'item', $this->id($path)];
        }
        if (str_starts_with($path, '/api/places') || str_starts_with($path, '/api/rooms')) {
            return ['location.'.$verb, str_starts_with($path, '/api/rooms') ? 'room' : 'place', $this->id($path)];
        }
        if (str_contains($path, '/shares')) {
            if ('POST' === $method && preg_match('#/items/(\d+)/shares$#', $path, $matches)) {
                return ['share.created', 'item', (int) $matches[1]];
            }

            return ['share.'.$verb, 'share', $this->id($path)];
        }
        if (str_contains($path, '/loans')) {
            return ['loan.'.$verb, 'loan', $this->id($path)];
        }
        if (str_starts_with($path, '/api/social')) {
            return ['social.'.$verb, 'collection', $this->id($path)];
        }
        if (str_starts_with($path, '/api/friends')) {
            return ['friends.'.$verb, 'connection', $this->id($path)];
        }
        if (str_starts_with($path, '/api/child')) {
            return ['child.'.$verb, 'child', $this->id($path)];
        }

        return ['api.'.strtolower($method), null, $this->id($path)];
    }

    private function id(string $path): ?int
    {
        if (preg_match('#/(\d+)(?:/|$)#', $path, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function sanitize(string $path): string
    {
        $segments = explode('/', $path);

        return implode('/', array_map(
            static fn (string $segment): string => preg_match('/^[A-Za-z0-9_-]{16,}$/', $segment) ? '{token}' : $segment,
            $segments,
        ));
    }
}
