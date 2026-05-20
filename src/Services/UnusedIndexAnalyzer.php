<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Services;

use Illuminate\Support\Facades\DB;

final class UnusedIndexAnalyzer
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUnusedIndexes(string $connection = null): array
    {
        try {
            $db = DB::connection($connection);
            $db->getPdo(); // Ensure connection is active
            $driver = $db->getDriverName();

            if ($driver === 'pgsql') {
                return $this->getPostgresUnusedIndexes($db);
            }

            if ($driver === 'mysql' || $driver === 'mariadb') {
                return $this->getMysqlUnusedIndexes($db);
            }
        } catch (\Throwable) {
            // Silently fail if we can't connect or driver is unsupported
            return [];
        }

        return [];
    }

    private function getPostgresUnusedIndexes($db): array
    {
        $sql = "
            SELECT
                relname AS table_name,
                indexrelname AS index_name,
                idx_scan AS usage_count,
                pg_size_pretty(pg_relation_size(i.indexrelid)) AS index_size
            FROM
                pg_stat_user_indexes ui
            JOIN pg_index i ON ui.indexrelid = i.indexrelid
            WHERE
                NOT indisunique
                AND idx_scan = 0
            ORDER BY
                pg_relation_size(i.indexrelid) DESC;
        ";

        try {
            $results = $db->select($sql);
            return array_map(fn($row) => (array) $row, $results);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getMysqlUnusedIndexes($db): array
    {
        // Requires performance_schema and sys schema access
        $sql = "
            SELECT 
                object_schema AS database_name,
                object_name AS table_name,
                index_name,
                0 AS usage_count
            FROM 
                sys.schema_unused_indexes
            WHERE 
                object_schema = DATABASE();
        ";

        try {
            $results = $db->select($sql);
            return array_map(fn($row) => (array) $row, $results);
        } catch (\Throwable) {
            return [];
        }
    }
}
