<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Services;

final class QueryFingerprint
{
    public function make(string $normalizedSql, string $connectionName, ?string $primaryTable): string
    {
        $payload = json_encode([
            'sql' => $normalizedSql,
            'connection' => $connectionName,
            'table' => $primaryTable,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }
}

