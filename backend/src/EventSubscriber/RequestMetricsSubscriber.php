<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RequestMetricsSubscriber implements EventSubscriberInterface
{
    private const STARTED_AT = '_performance_metric_started_at';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 100],
            KernelEvents::RESPONSE => ['onResponse', -100],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $event->getRequest()->attributes->set(self::STARTED_AT, microtime(true));
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if ((!str_starts_with($path, '/api/') && !str_starts_with($path, '/admin'))
            || str_starts_with($path, '/api/analytics/visit')
        ) {
            return;
        }

        $startedAt = $request->attributes->get(self::STARTED_AT);
        if (!\is_float($startedAt)) {
            return;
        }

        $route = $request->attributes->get('_route');
        $routeName = \is_string($route) ? mb_substr($route, 0, 120) : 'unmatched';
        $method = mb_substr($request->getMethod(), 0, 10);
        $statusCode = $event->getResponse()->getStatusCode();
        $durationMs = max(0, (int) round((microtime(true) - $startedAt) * 1000));

        try {
            $this->upsert(
                $this->em->getConnection(),
                (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d'),
                $routeName,
                $method,
                $statusCode,
                $statusCode >= 500 ? 1 : 0,
                $durationMs,
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Could not record request performance metrics.', [
                'exception' => $exception,
                'route' => $routeName,
            ]);
        }
    }

    private function upsert(
        Connection $connection,
        string $date,
        string $route,
        string $method,
        int $statusCode,
        int $errorCount,
        int $durationMs,
    ): void {
        $platform = $connection->getDatabasePlatform();
        $base = 'INSERT INTO performance_metric (bucket_date, route_name, method, status_code, request_count, error_count, total_duration_ms, max_duration_ms) VALUES (:date, :route, :method, :status, 1, :errors, :duration, :duration)';

        if ($platform instanceof AbstractMySQLPlatform) {
            $sql = $base.' ON DUPLICATE KEY UPDATE request_count = request_count + 1, error_count = error_count + VALUES(error_count), total_duration_ms = total_duration_ms + VALUES(total_duration_ms), max_duration_ms = GREATEST(max_duration_ms, VALUES(max_duration_ms))';
        } elseif ($platform instanceof SQLitePlatform) {
            $sql = $base.' ON CONFLICT (bucket_date, route_name, method, status_code) DO UPDATE SET request_count = request_count + 1, error_count = error_count + excluded.error_count, total_duration_ms = total_duration_ms + excluded.total_duration_ms, max_duration_ms = MAX(max_duration_ms, excluded.max_duration_ms)';
        } else {
            throw new \LogicException(sprintf('Unsupported database platform for request metrics: %s.', $platform::class));
        }

        $connection->executeStatement($sql, [
            'date' => $date,
            'route' => $route,
            'method' => $method,
            'status' => $statusCode,
            'errors' => $errorCount,
            'duration' => $durationMs,
        ]);
    }
}
