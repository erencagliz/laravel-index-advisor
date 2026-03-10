<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\DTO;

final class ObservedQuery
{
    public function __construct(
        public readonly string $connectionName,
        public readonly string $rawSql,
        public readonly string $normalizedSql,
        public readonly string $fingerprint,
        public readonly float $executionTimeMs,
        public readonly int $bindingsCount,
        public readonly ?string $routeName,
        public readonly ?string $requestPath,
        public readonly ?string $httpMethod,
        public readonly ?string $jobClass,
        public readonly ?int $userId,
        public readonly \DateTimeImmutable $observedAt,
    ) {
    }
}

