<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ReportCommand extends Command
{
    protected $signature = 'index-advisor:report
        {--table= : Filter by table name}
        {--connection= : Filter by connection name}
        {--limit=50 : Maximum number of rows}
        {--json : Output as JSON}';

    protected $description = 'Show aggregated query statistics collected by Laravel Index Advisor.';

    public function handle(): int
    {
        $query = DB::table('index_advisor_queries')->orderByDesc('executions');

        if ($table = $this->option('table')) {
            $query->where('table_name', $table);
        }

        if ($connection = $this->option('connection')) {
            $query->where('connection_name', $connection);
        }

        $limit = (int) ($this->option('limit') ?? 50);
        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->get();

        if ($this->option('json')) {
            $this->line($rows->toJson(JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if ($rows->isEmpty()) {
            $this->info('No query data found for the given filters.');

            return self::SUCCESS;
        }

        $this->table(
            ['Table', 'Connection', 'Fingerprint', 'Executions', 'Avg (ms)', 'P95 (ms)'],
            $rows->map(function ($row): array {
                return [
                    $row->table_name ?? '(unknown)',
                    $row->connection_name,
                    substr($row->fingerprint, 0, 12),
                    $row->executions,
                    $row->avg_time_ms,
                    $row->p95_time_ms ?? $row->avg_time_ms,
                ];
            })->toArray()
        );

        $this->newLine();
        $this->line('Use --json for machine-readable output or inspect individual queries via your database.');

        return self::SUCCESS;
    }
}

