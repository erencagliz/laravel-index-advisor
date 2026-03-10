<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class WorkloadAnalyzer
{
    /**
     * Build a simple workload profile over the given lookback window.
     *
     * @return array<string, mixed>
     */
    public function profile(int $days = 7): array
    {
        $cutoff = CarbonImmutable::now()->subDays($days);

        $queries = DB::table('index_advisor_queries')
            ->where('last_seen_at', '>=', $cutoff)
            ->get();

        if ($queries->isEmpty()) {
            return [
                'summary' => [
                    'period_days' => $days,
                    'total_fingerprints' => 0,
                    'total_executions' => 0,
                    'profile' => 'unknown',
                ],
                'tables' => [],
            ];
        }

        $totalExecutions = 0;
        $tableStats = [];

        foreach ($queries as $row) {
            $table = $row->table_name ?? '(unknown)';

            if (! isset($tableStats[$table])) {
                $tableStats[$table] = [
                    'table' => $table,
                    'executions' => 0,
                    'total_time_ms' => 0,
                    'avg_time_ms' => 0.0,
                ];
            }

            $tableStats[$table]['executions'] += (int) $row->executions;
            $tableStats[$table]['total_time_ms'] += (int) $row->total_time_ms;
            $totalExecutions += (int) $row->executions;
        }

        foreach ($tableStats as &$stats) {
            $stats['avg_time_ms'] = $stats['executions'] > 0
                ? $stats['total_time_ms'] / $stats['executions']
                : 0.0;
        }
        unset($stats);

        usort($tableStats, static function (array $a, array $b): int {
            return $b['executions'] <=> $a['executions'];
        });

        $profile = 'read-heavy';

        return [
            'summary' => [
                'period_days' => $days,
                'total_fingerprints' => $queries->count(),
                'total_executions' => $totalExecutions,
                'profile' => $profile,
            ],
            'tables' => $tableStats,
        ];
    }
}

