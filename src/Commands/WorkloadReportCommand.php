<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Commands;

use Erencagliz\LaravelIndexAdvisor\Services\WorkloadAnalyzer;
use Illuminate\Console\Command;

final class WorkloadReportCommand extends Command
{
    protected $signature = 'index-advisor:workload
        {--days=7 : Lookback window in days}
        {--json : Output as JSON}';

    protected $description = 'Show a high-level workload profile based on collected query aggregates.';

    public function __construct(
        private readonly WorkloadAnalyzer $analyzer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $profile = $this->analyzer->profile($days);

        if ($this->option('json')) {
            $this->line(json_encode($profile, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $summary = $profile['summary'] ?? [];
        $tables = $profile['tables'] ?? [];

        $this->info(sprintf(
            'Workload profile for last %d day(s): %s',
            $summary['period_days'] ?? $days,
            $summary['profile'] ?? 'unknown'
        ));

        $this->line(sprintf(
            'Total fingerprints: %d, total executions: %d',
            $summary['total_fingerprints'] ?? 0,
            $summary['total_executions'] ?? 0
        ));
        $this->newLine();

        if ($tables === []) {
            $this->info('No query data found in the given period.');

            return self::SUCCESS;
        }

        $this->table(
            ['Table', 'Executions', 'Total time (ms)', 'Avg time (ms)'],
            array_map(
                static function (array $t): array {
                    return [
                        $t['table'],
                        $t['executions'],
                        $t['total_time_ms'],
                        round($t['avg_time_ms'], 2),
                    ];
                },
                $tables
            )
        );

        return self::SUCCESS;
    }
}

