<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Commands;

use Erencagliz\LaravelIndexAdvisor\Contracts\SuggestionEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SuggestCommand extends Command
{
    protected $signature = 'index-advisor:suggest
        {--table= : Filter by table name}
        {--connection= : Filter by connection name}
        {--min-score=60 : Minimum confidence score}
        {--json : Output as JSON}
        {--persist : Persist suggestions to the suggestions table}';

    protected $description = 'Generate index suggestions based on aggregated query statistics.';

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

        $allSuggestions = [];

        foreach ($rows as $row) {
            $suggestions = $this->engine->suggestForFingerprint($row->fingerprint);

            foreach ($suggestions as $suggestion) {
                if ($suggestion->confidenceScore < $minScore) {
                    continue;
                }

                $persisted = null;

                if ($this->option('persist')) {
                    $persisted = $this->persistSuggestion($row, $suggestion);
                }

                $allSuggestions[] = (object) array_merge(
                    [
                        'id' => $persisted?->id ?? null,
                        'connection_name' => $row->connection_name,
                        'status' => $persisted->status ?? 'pending',
                    ],
                    [
                        'table' => $suggestion->table,
                        'columns' => $suggestion->columns,
                        'indexType' => $suggestion->indexType,
                        'reason' => $suggestion->reason,
                        'confidenceScore' => $suggestion->confidenceScore,
                        'fingerprint' => $suggestion->fingerprint,
                        'supportingStats' => $suggestion->supportingStats,
                        'existingSimilarIndexes' => $suggestion->existingSimilarIndexes,
                        'warnings' => $suggestion->warnings,
                    ]
                );
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode(
                array_map(
                    static function ($s): array {
                        return [
                            'table' => $s->table,
                            'columns' => $s->columns,
                            'index_type' => $s->indexType,
                            'reason' => $s->reason,
                            'confidence' => $s->confidenceScore,
                            'fingerprint' => $s->fingerprint,
                            'supporting_stats' => $s->supportingStats,
                            'existing_similar_indexes' => $s->existingSimilarIndexes,
                            'warnings' => $s->warnings,
                        ];
                    },
                    $allSuggestions
                ),
                JSON_PRETTY_PRINT
            ));

            return self::SUCCESS;
        }

        if ($allSuggestions === []) {
            $this->info('No suggestions found for the given filters.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Table', 'Connection', 'Columns', 'Type', 'Confidence', 'Status', 'Reason'],
            array_map(
                static function ($s): array {
                    return [
                        $s->id ?? '-',
                        $s->table,
                        $s->connection_name ?? '-',
                        implode(', ', $s->columns),
                        $s->indexType,
                        $s->confidenceScore,
                        $s->status ?? 'pending',
                        mb_strimwidth($s->reason, 0, 80, '...'),
                    ];
                },
                $allSuggestions
            )
        );

        $this->newLine();
        $this->line('Use --json for full machine-readable details or --persist to store suggestions.');

        return self::SUCCESS;
    }

    private function persistSuggestion(object $row, object $suggestion): ?object
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

            return DB::table('index_advisor_suggestions')->where('id', $existing->id)->first();
        }

        $id = DB::table('index_advisor_suggestions')->insertGetId([
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

        return DB::table('index_advisor_suggestions')->where('id', $id)->first();
    }
}

