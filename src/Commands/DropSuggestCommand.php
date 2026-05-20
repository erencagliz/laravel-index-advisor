<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Commands;

use Erencagliz\LaravelIndexAdvisor\Services\UnusedIndexAnalyzer;
use Illuminate\Console\Command;

final class DropSuggestCommand extends Command
{
    protected $signature = 'index-advisor:drop-suggest
        {--connection= : Filter by connection name}
        {--json : Output as JSON}';

    protected $description = 'Suggest unused indexes to drop based on database internal statistics.';

    public function __construct(
        private readonly UnusedIndexAnalyzer $analyzer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $connection = $this->option('connection');
        
        $unusedIndexes = $this->analyzer->getUnusedIndexes($connection);

        if ($this->option('json')) {
            $this->line(json_encode($unusedIndexes, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        if ($unusedIndexes === []) {
            $this->info('No unused indexes found or the driver does not support this feature.');
            return self::SUCCESS;
        }

        $this->info('Found potentially unused indexes:');
        
        $headers = array_keys($unusedIndexes[0]);
        $rows = array_map('array_values', $unusedIndexes);
        
        $this->table($headers, $rows);

        $this->newLine();
        $this->warn('Warning: Always verify index usage before dropping. Some indexes might be used rarely (e.g., monthly reports).');

        return self::SUCCESS;
    }
}
