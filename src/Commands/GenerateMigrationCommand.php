<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Commands;

use Erencagliz\LaravelIndexAdvisor\DTO\IndexSuggestion;
use Erencagliz\LaravelIndexAdvisor\Services\MigrationStubGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GenerateMigrationCommand extends Command
{
    protected $signature = 'index-advisor:generate-migration
        {--suggestion= : ID of the suggestion}
        {--name= : Custom migration class base name}';

    protected $description = 'Generate a Laravel migration file from an index suggestion.';

    public function __construct(
        private readonly MigrationStubGenerator $generator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $id = $this->option('suggestion');

        if ($id === null) {
            $this->error('The --suggestion option is required.');

            return self::FAILURE;
        }

        $row = DB::table('index_advisor_suggestions')->where('id', $id)->first();

        if ($row === null) {
            $this->error('Suggestion not found for ID ' . $id);

            return self::FAILURE;
        }

        $columns = json_decode($row->suggested_columns, true, 512, JSON_THROW_ON_ERROR);

        $suggestion = new IndexSuggestion(
            table: $row->table_name,
            columns: $columns,
            indexType: $row->suggested_index_type,
            reason: $row->reason,
            confidenceScore: (int) $row->confidence_score,
            fingerprint: $row->fingerprint,
            supportingStats: [],
            existingSimilarIndexes: [],
            warnings: [],
        );

        $customName = $this->option('name');

        $classSource = $this->generator->buildMigrationClass($suggestion, $customName);

        $fileName = $this->buildFileName($row->table_name, $customName);
        $path = $this->laravel->databasePath('migrations/' . $fileName);

        if (file_exists($path)) {
            $this->error('Migration file already exists: ' . $path);

            return self::FAILURE;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $classSource);

        $this->info('Migration created: ' . $path);

        return self::SUCCESS;
    }

    private function buildFileName(string $table, ?string $customName): string
    {
        $base = $customName
            ? Str::snake($customName)
            : 'add_index_to_' . Str::snake($table) . '_table';

        $timestamp = date('Y_m_d_His');

        return $timestamp . '_' . $base . '.php';
    }
}

