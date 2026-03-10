<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor;

use Erencagliz\LaravelIndexAdvisor\Contracts\SuggestionEngine;
use Illuminate\Support\Facades\DB;

final class IndexAdvisor
{
    public function __construct(
        private readonly SuggestionEngine $engine,
    ) {
    }

    /**
     * Get suggestions for all stored fingerprints with optional filters.
     *
     * @return array<int, array<string, mixed>>
     */
    public function suggest(array $filters = []): array
    {
        $query = DB::table('index_advisor_queries')->orderByDesc('executions');

        if (isset($filters['table'])) {
            $query->where('table_name', $filters['table']);
        }

        if (isset($filters['connection'])) {
            $query->where('connection_name', $filters['connection']);
        }

        $minScore = (int) ($filters['min_score'] ?? 60);

        $rows = $query->get();

        $result = [];

        foreach ($rows as $row) {
            $suggestions = $this->engine->suggestForFingerprint($row->fingerprint);

            foreach ($suggestions as $suggestion) {
                if ($suggestion->confidenceScore < $minScore) {
                    continue;
                }

                $result[] = [
                    'table' => $suggestion->table,
                    'columns' => $suggestion->columns,
                    'index_type' => $suggestion->indexType,
                    'reason' => $suggestion->reason,
                    'confidence' => $suggestion->confidenceScore,
                    'fingerprint' => $suggestion->fingerprint,
                    'supporting_stats' => $suggestion->supportingStats,
                    'existing_similar_indexes' => $suggestion->existingSimilarIndexes,
                    'warnings' => $suggestion->warnings,
                ];
            }
        }

        return $result;
    }
}

