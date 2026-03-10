<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Services;

use Erencagliz\LaravelIndexAdvisor\Contracts\IgnoreRepository;
use Erencagliz\LaravelIndexAdvisor\Contracts\SchemaIndexRepository;
use Erencagliz\LaravelIndexAdvisor\Contracts\SuggestionEngine;
use Erencagliz\LaravelIndexAdvisor\DTO\IndexSuggestion;
use Erencagliz\LaravelIndexAdvisor\Parsers\SqlShapeParser;
use Illuminate\Support\Facades\DB;

final class DefaultSuggestionEngine implements SuggestionEngine
{
    public function __construct(
        private readonly SchemaIndexRepository $schema,
        private readonly IgnoreRepository $ignores,
        private readonly SqlShapeParser $parser,
        private readonly IndexNameGenerator $indexNameGenerator,
        private readonly ExplainAnalyzer $explainAnalyzer,
    ) {
    }

    public function suggestForFingerprint(string $fingerprint): array
    {
        $row = DB::table('index_advisor_queries')->where('fingerprint', $fingerprint)->first();

        if ($row === null) {
            return [];
        }

        $connection = $row->connection_name;
        $normalizedSql = $row->normalized_sql;

        $shape = $this->parser->parse($normalizedSql);

        if ($shape->primaryTable === null) {
            return [];
        }

        $minExecutions = (int) config('index-advisor.min_executions', 25);
        if ((int) $row->executions < $minExecutions) {
            return [];
        }

        $suggestions = [];

        $suggestions = array_merge(
            $suggestions,
            $this->suggestEqualityFilters($connection, $row->table_name ?? $shape->primaryTable, $shape, $row)
        );

        return $suggestions;
    }

    private function suggestEqualityFilters(string $connection, string $table, $shape, $row): array
    {
        $whereColumns = $shape->whereColumns;
        $orderByColumns = $shape->orderByColumns;

        $suggestions = [];

        if (count($whereColumns) === 1) {
            $columns = [$whereColumns[0]];

            if (! $this->ignores->shouldIgnoreSuggestion($table, $columns, $row->fingerprint)
                && ! $this->schema->hasExactIndex($connection, $table, $columns)
            ) {
                $similar = $this->schema->findSimilarIndexes($connection, $table, $columns);
                $explain = $this->explainAnalyzer->analyze($connection, $row->sample_raw_sql ?? $row->normalized_sql);
                $confidence = $this->computeConfidence($row, $similar, $shape, $explain);

                if ($confidence >= 40) {
                    $suggestions[] = $this->buildSuggestion(
                        $table,
                        $columns,
                        'index',
                        'Frequent equality filter without an exact index.',
                        $confidence,
                        $row,
                        $similar,
                        []
                    );
                }
            }
        }

        if (count($whereColumns) >= 2) {
            $equalityColumns = array_values($whereColumns);
            $columns = $equalityColumns;

            if ($orderByColumns !== []) {
                $columns = array_merge($columns, $orderByColumns);
            }

            if (! $this->ignores->shouldIgnoreSuggestion($table, $columns, $row->fingerprint)
                && ! $this->schema->hasExactIndex($connection, $table, $columns)
            ) {
                $similar = $this->schema->findSimilarIndexes($connection, $table, $columns);
                $explain = $this->explainAnalyzer->analyze($connection, $row->sample_raw_sql ?? $row->normalized_sql);
                $confidence = $this->computeConfidence($row, $similar, $shape, $explain);

                if ($confidence >= 40) {
                    $reason = $orderByColumns === []
                        ? 'Frequent multi-column equality filter without composite index.'
                        : 'Frequent equality filters followed by sorting; composite index can cover both filter and sort.';

                    $suggestions[] = $this->buildSuggestion(
                        $table,
                        $columns,
                        'index',
                        $reason,
                        $confidence,
                        $row,
                        $similar,
                        []
                    );
                }
            }
        }

        return $suggestions;
    }

    private function computeConfidence($row, array $similarIndexes, $shape, ?array $explain): int
    {
        $score = 50;

        $executions = (int) $row->executions;
        $avgTime = (float) $row->avg_time_ms;
        $p95 = (float) ($row->p95_time_ms ?? $avgTime);

        if ($executions > 1000) {
            $score += 15;
        } elseif ($executions > 100) {
            $score += 10;
        } elseif ($executions > 25) {
            $score += 5;
        }

        if ($avgTime > 50) {
            $score += 10;
        }

        if ($p95 > 100) {
            $score += 10;
        }

        if (count($similarIndexes) > 0) {
            $score -= 15;
        }

        if ($shape->hasSubquery) {
            $score -= 10;
        }

        if ($explain !== null) {
            if (! empty($explain['has_full_scan'])) {
                $score += 10;
            }

            if (! empty($explain['using_filesort']) || ! empty($explain['using_temporary'])) {
                $score += 5;
            }

            $rowsExamined = (int) ($explain['rows'] ?? 0);
            if ($rowsExamined > 100_000) {
                $score += 10;
            } elseif ($rowsExamined > 10_000) {
                $score += 5;
            }
        }

        return max(0, min(100, (int) round($score)));
    }

    private function buildSuggestion(
        string $table,
        array $columns,
        string $indexType,
        string $reason,
        int $confidence,
        $row,
        array $similarIndexes,
        array $warnings,
    ): IndexSuggestion {
        $indexName = $this->indexNameGenerator->generate($table, $columns, $indexType);

        $explain = null;
        if (! empty($row->sample_raw_sql)) {
            $explain = $this->explainAnalyzer->analyze($row->connection_name, $row->sample_raw_sql);
        }

        return new IndexSuggestion(
            table: $table,
            columns: $columns,
            indexType: $indexType,
            reason: $reason,
            confidenceScore: $confidence,
            fingerprint: $row->fingerprint,
            supportingStats: [
                'executions' => (int) $row->executions,
                'avg_time_ms' => (float) $row->avg_time_ms,
                'p95_time_ms' => (float) ($row->p95_time_ms ?? $row->avg_time_ms),
                'explain' => $explain,
            ],
            existingSimilarIndexes: $similarIndexes,
            warnings: $warnings,
        );
    }
}

