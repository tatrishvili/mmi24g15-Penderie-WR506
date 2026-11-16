<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'performance_metric')]
#[ORM\UniqueConstraint(
    name: 'uniq_performance_bucket',
    columns: ['bucket_date', 'route_name', 'method', 'status_code'],
)]
#[ORM\Index(name: 'performance_bucket_date', columns: ['bucket_date'])]
class PerformanceMetric
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $bucketDate;

    #[ORM\Column(length: 120)]
    private string $routeName;

    #[ORM\Column(length: 10)]
    private string $method;

    #[ORM\Column(type: 'smallint')]
    private int $statusCode;

    #[ORM\Column]
    private int $requestCount = 0;

    #[ORM\Column]
    private int $errorCount = 0;

    #[ORM\Column]
    private int $totalDurationMs = 0;

    #[ORM\Column]
    private int $maxDurationMs = 0;

    public function __construct(
        \DateTimeImmutable $bucketDate,
        string $routeName,
        string $method,
        int $statusCode,
    ) {
        $this->bucketDate = $bucketDate;
        $this->routeName = $routeName;
        $this->method = $method;
        $this->statusCode = $statusCode;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBucketDate(): \DateTimeImmutable
    {
        return $this->bucketDate;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function getTotalDurationMs(): int
    {
        return $this->totalDurationMs;
    }

    public function getMaxDurationMs(): int
    {
        return $this->maxDurationMs;
    }
}
