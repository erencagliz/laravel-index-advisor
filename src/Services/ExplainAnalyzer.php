<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Services;

use Illuminate\Support\Facades\DB;

final class ExplainAnalyzer
{
    /**
     * @return array<string, mixed>|null
     */
    public function analyze(string $connection, string $rawSql): ?array
    {
        if (! config('index-advisor.explain.enabled', false)) {
            return null;
        }

        $sql = ltrim(strtolower($rawSql));

        if (! str_starts_with($sql, 'select')) {
            return null;
        }

        try {
            $rows = DB::connection($connection)->select('EXPLAIN ' . $rawSql);
        } catch (\Throwable) {
            return null;
        }

        if ($rows === []) {
            return null;
        }

        $row = (array) $rows[0];

        $rowsExamined = (int) ($row['rows'] ?? 0);
        $type = (string) ($row['type'] ?? ($row['access_type'] ?? ''));
        $extra = strtolower((string) ($row['Extra'] ?? ($row['extra'] ?? '')));

        return [
            'raw' => $rows,
            'rows' => $rowsExamined,
            'type' => $type,
            'has_full_scan' => $type === 'ALL',
            'has_range_scan' => $type === 'range',
            'using_filesort' => str_contains($extra, 'filesort'),
            'using_temporary' => str_contains($extra, 'temporary'),
        ];
    }
}

