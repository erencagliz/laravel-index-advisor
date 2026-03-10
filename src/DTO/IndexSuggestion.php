<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\DTO;

final class IndexSuggestion
{
    /**
     * @param string[] $columns
     * @param array<string, mixed> $supportingStats
     * @param array<string, mixed> $existingSimilarIndexes
     * @param string[] $warnings
     */
    public function __construct(
        public readonly string $table,
        public readonly array $columns,
        public readonly string $indexType,
        public readonly string $reason,
        public readonly int $confidenceScore,
        public readonly string $fingerprint,
        public readonly array $supportingStats,
        public readonly array $existingSimilarIndexes,
        public readonly array $warnings,
    ) {
    }
}

