<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Commands;

use Erencagliz\LaravelIndexAdvisor\Contracts\SuggestionEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class CheckCICommand extends Command
{
    protected $signature = 'index-advisor:check-ci
        {--min-score=70 : Minimum confidence score to trigger failure}';

    protected $description = 'Check for missing critical indexes. Fails the build if found. Ideal for CI/CD pipelines.';

    public function __construct(
        private readonly SuggestionEngine $engine,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $minScore = (int) $this->option('min-score');
        
        $this->info("Running Index Advisor CI Check (threshold: {$minScore})...");

        $rows = DB::table('index_advisor_queries')->cursor();
        $criticalSuggestions = [];

        foreach ($rows as $row) {
            $suggestions = $this->engine->suggestForFingerprint($row->fingerprint);

            foreach ($suggestions as $suggestion) {
                if ($suggestion->confidenceScore >= $minScore) {
                    $criticalSuggestions[] = $suggestion;
                }
            }
        }

        if (count($criticalSuggestions) > 0) {
            $this->error('CRITICAL: Missing indexes detected during test execution!');
            
            foreach ($criticalSuggestions as $s) {
                $columns = implode(', ', $s->columns);
                $this->error("- Table '{$s->table}' is missing a '{$s->indexType}' index on columns: [{$columns}] (Score: {$s->confidenceScore})");
            }
            
            $this->warn('Please add the missing indexes before merging this PR.');
            return self::FAILURE;
        }

        $this->info('Success! No critical missing indexes found.');
        return self::SUCCESS;
    }
}
