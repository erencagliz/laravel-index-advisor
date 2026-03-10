<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Parsers;

use Erencagliz\LaravelIndexAdvisor\DTO\ParsedQueryShape;

final class AstQueryShapeBuilder
{
    /**
     * @param array<string, mixed> $ast
     */
    public function build(array $ast): ParsedQueryShape
    {
        $operationType = $this->detectOperationType($ast);
        $tables = $this->extractTables($ast);
        $whereColumns = $this->extractWhereColumns($ast);
        $joinColumns = $this->extractJoinColumns($ast);
        $orderByColumns = $this->extractOrderByColumns($ast);
        $groupByColumns = $this->extractGroupByColumns($ast);
        $limit = $this->extractLimit($ast);

        $hasSubquery = $this->hasSubquery($ast);
        $warnings = [];

        if ($hasSubquery) {
            $warnings[] = 'Subquery detected; analysis may be incomplete.';
        }

        return new ParsedQueryShape(
            operationType: $operationType,
            primaryTable: $tables[0] ?? null,
            involvedTables: array_values(array_unique($tables)),
            whereColumns: array_values(array_unique($whereColumns)),
            joinColumns: array_values(array_unique($joinColumns)),
            orderByColumns: array_values(array_unique($orderByColumns)),
            groupByColumns: array_values(array_unique($groupByColumns)),
            limit: $limit,
            hasSubquery: $hasSubquery,
            parseWarnings: $warnings,
        );
    }

    /**
     * @param array<string, mixed> $ast
     */
    private function detectOperationType(array $ast): string
    {
        if (isset($ast['SELECT'])) {
            return 'select';
        }

        if (isset($ast['UPDATE'])) {
            return 'update';
        }

        if (isset($ast['DELETE'])) {
            return 'delete';
        }

        if (isset($ast['INSERT'])) {
            return 'insert';
        }

        return 'unknown';
    }

    /**
     * @param array<string, mixed> $ast
     * @return string[]
     */
    private function extractTables(array $ast): array
    {
        $tables = [];

        if (! isset($ast['FROM']) || ! is_array($ast['FROM'])) {
            return $tables;
        }

        foreach ($ast['FROM'] as $from) {
            if (! is_array($from)) {
                continue;
            }

            if (($from['expr_type'] ?? null) === 'table') {
                $tables[] = (string) ($from['table'] ?? '');
            }
        }

        return $tables;
    }

    /**
     * @param array<string, mixed> $ast
     * @return string[]
     */
    private function extractWhereColumns(array $ast): array
    {
        $columns = [];

        if (! isset($ast['WHERE']) || ! is_array($ast['WHERE'])) {
            return $columns;
        }

        foreach ($ast['WHERE'] as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (($node['expr_type'] ?? null) === 'colref') {
                $baseExpr = (string) ($node['base_expr'] ?? '');

                // php-sql-parser sometimes represents placeholders as "?"
                if ($baseExpr === '?' || $baseExpr === '') {
                    continue;
                }

                $columns[] = $baseExpr;
            }
        }

        return $columns;
    }

    /**
     * @param array<string, mixed> $ast
     * @return string[]
     */
    private function extractJoinColumns(array $ast): array
    {
        $columns = [];

        if (! isset($ast['FROM']) || ! is_array($ast['FROM'])) {
            return $columns;
        }

        foreach ($ast['FROM'] as $from) {
            if (! is_array($from)) {
                continue;
            }

            if (! isset($from['ref_clause']) || ! is_array($from['ref_clause'])) {
                continue;
            }

            foreach ($from['ref_clause'] as $clause) {
                if (! is_array($clause)) {
                    continue;
                }

                if (($clause['expr_type'] ?? null) === 'colref') {
                    $columns[] = (string) ($clause['base_expr'] ?? '');
                }
            }
        }

        return $columns;
    }

    /**
     * @param array<string, mixed> $ast
     * @return string[]
     */
    private function extractOrderByColumns(array $ast): array
    {
        $columns = [];

        if (! isset($ast['ORDER']) || ! is_array($ast['ORDER'])) {
            return $columns;
        }

        foreach ($ast['ORDER'] as $order) {
            if (! is_array($order)) {
                continue;
            }

            if (($order['expr_type'] ?? null) === 'colref') {
                $columns[] = (string) ($order['base_expr'] ?? '');
            }
        }

        return $columns;
    }

    /**
     * @param array<string, mixed> $ast
     * @return string[]
     */
    private function extractGroupByColumns(array $ast): array
    {
        $columns = [];

        if (! isset($ast['GROUP']) || ! is_array($ast['GROUP'])) {
            return $columns;
        }

        foreach ($ast['GROUP'] as $group) {
            if (! is_array($group)) {
                continue;
            }

            if (($group['expr_type'] ?? null) === 'colref') {
                $columns[] = (string) ($group['base_expr'] ?? '');
            }
        }

        return $columns;
    }

    /**
     * @param array<string, mixed> $ast
     */
    private function extractLimit(array $ast): ?int
    {
        if (! isset($ast['LIMIT']) || ! is_array($ast['LIMIT'])) {
            return null;
        }

        $row = $ast['LIMIT']['rowcount'] ?? null;

        if (is_array($row) && isset($row['base_expr'])) {
            return (int) $row['base_expr'];
        }

        if (is_scalar($row)) {
            return (int) $row;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $ast
     */
    private function hasSubquery(array $ast): bool
    {
        $json = json_encode($ast);

        if (! is_string($json)) {
            return false;
        }

        return str_contains(strtolower($json), 'subquery');
    }
}

