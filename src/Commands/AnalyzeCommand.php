<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Commands;

use Erencagliz\LaravelIndexAdvisor\Contracts\SuggestionEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class AnalyzeCommand extends Command
{
    protected $signature = 'index-advisor:analyze
        {--table= : Filter by table name}
        {--connection= : Filter by connection name}
        {--min-score=60 : Minimum confidence score}';

    protected $description = 'Analyze stored query data and populate the suggestions table.';

    public function __construct(
        private readonly SuggestionEngine $engine,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $minScore = (int) $this->option('min-score');

        $query = DB::table('index_advisor_queries')->orderByDesc('executions');

        if ($table = $this->option('table')) {
            $query->where('table_name', $table);
        }

        if ($connection = $this->option('connection')) {
            $query->where('connection_name', $connection);
        }

        $rows = $query->get();

        $count = 0;

        foreach ($rows as $row) {
            $suggestions = $this->engine->suggestForFingerprint($row->fingerprint);

            foreach ($suggestions as $suggestion) {
                if ($suggestion->confidenceScore < $minScore) {
                    continue;
                }

                $this->persistSuggestion($row, $suggestion);
                $count++;
            }
        }

        $this->info(sprintf('Analysis complete. %d suggestions stored.', $count));

        return self::SUCCESS;
    }

    private function persistSuggestion(object $row, object $suggestion): void
    {
        $existing = DB::table('index_advisor_suggestions')
            ->where('fingerprint', $suggestion->fingerprint)
            ->where('table_name', $suggestion->table)
            ->where('suggested_columns', json_encode($suggestion->columns))
            ->first();

        $now = now();

        if ($existing !== null) {
            DB::table('index_advisor_suggestions')
                ->where('id', $existing->id)
                ->update([
                    'reason' => $suggestion->reason,
                    'confidence_score' => $suggestion->confidenceScore,
                    'supporting_stats' => json_encode($suggestion->supportingStats),
                    'similar_existing_indexes' => json_encode($suggestion->existingSimilarIndexes),
                    'warnings' => json_encode($suggestion->warnings),
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('index_advisor_suggestions')->insert([
            'fingerprint' => $suggestion->fingerprint,
            'connection_name' => $row->connection_name,
            'table_name' => $suggestion->table,
            'suggested_columns' => json_encode($suggestion->columns),
            'suggested_index_type' => $suggestion->indexType,
            'suggested_index_name' => null,
            'reason' => $suggestion->reason,
            'confidence_score' => $suggestion->confidenceScore,
            'supporting_stats' => json_encode($suggestion->supportingStats),
            'similar_existing_indexes' => json_encode($suggestion->existingSimilarIndexes),
            'warnings' => json_encode($suggestion->warnings),
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

