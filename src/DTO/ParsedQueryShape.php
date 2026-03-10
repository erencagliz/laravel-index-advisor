<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\DTO;

final class ParsedQueryShape
{
    /**
     * @param string[] $involvedTables
     * @param string[] $whereColumns
     * @param string[] $joinColumns
     * @param string[] $orderByColumns
     * @param string[] $groupByColumns
     * @param string[] $parseWarnings
     */
    public function __construct(
        public readonly string $operationType,
        public readonly ?string $primaryTable,
        public readonly array $involvedTables,
        public readonly array $whereColumns,
        public readonly array $joinColumns,
        public readonly array $orderByColumns,
        public readonly array $groupByColumns,
        public readonly ?int $limit,
        public readonly bool $hasSubquery,
        public readonly array $parseWarnings,
    ) {
    }
}

