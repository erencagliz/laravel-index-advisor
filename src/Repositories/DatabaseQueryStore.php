<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Repositories;

use Carbon\CarbonImmutable;
use Erencagliz\LaravelIndexAdvisor\Contracts\QueryStore;
use Erencagliz\LaravelIndexAdvisor\DTO\ObservedQuery;
use Illuminate\Support\Facades\DB;

final class DatabaseQueryStore implements QueryStore
{
    public function record(ObservedQuery $query): void
    {
        $now = CarbonImmutable::now();

        $existing = DB::table('index_advisor_queries')
            ->where('fingerprint', $query->fingerprint)
            ->first();

        if ($existing === null) {
            DB::table('index_advisor_queries')->insert([
                'fingerprint' => $query->fingerprint,
                'connection_name' => $query->connectionName,
                'table_name' => null,
                'normalized_sql' => $query->normalizedSql,
                'sample_raw_sql' => config('index-advisor.store_raw_sql_sample', false)
                    ? $query->rawSql
                    : null,
                'executions' => 1,
                'total_time_ms' => (int) round($query->executionTimeMs),
                'avg_time_ms' => $query->executionTimeMs,
                'max_time_ms' => (int) round($query->executionTimeMs),
                'p95_time_ms' => (int) round($query->executionTimeMs),
                'first_seen_at' => $query->observedAt,
                'last_seen_at' => $query->observedAt,
                'parse_status' => 'pending',
                'parse_warnings' => null,
                'shape' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        $executions = (int) $existing->executions + 1;
        $totalTime = (int) $existing->total_time_ms + (int) round($query->executionTimeMs);
        $avgTime = $executions > 0 ? $totalTime / $executions : 0.0;
        $maxTime = max((int) $existing->max_time_ms, (int) round($query->executionTimeMs));

        DB::table('index_advisor_queries')
            ->where('id', $existing->id)
            ->update([
                'executions' => $executions,
                'total_time_ms' => $totalTime,
                'avg_time_ms' => $avgTime,
                'max_time_ms' => $maxTime,
                'last_seen_at' => $query->observedAt,
                'updated_at' => $now,
            ]);
    }
}

