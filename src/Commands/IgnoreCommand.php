<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class IgnoreCommand extends Command
{
    protected $signature = 'index-advisor:ignore
        {--fingerprint= : Fingerprint to ignore}
        {--table= : Table name to ignore}
        {--columns= : Comma-separated columns to ignore for a table}
        {--reason= : Optional reason for ignore entry}';

    protected $description = 'Add an ignore rule for specific fingerprints, tables, or column patterns.';

    public function handle(): int
    {
        $fingerprint = (string) ($this->option('fingerprint') ?? '');
        $table = (string) ($this->option('table') ?? '');
        $columnsOption = (string) ($this->option('columns') ?? '');

        if ($fingerprint === '' && $table === '' && $columnsOption === '') {
            $this->error('You must provide at least one of --fingerprint, --table, or --columns.');

            return self::FAILURE;
        }

        $type = null;
        $columns = null;

        if ($fingerprint !== '') {
            $type = 'fingerprint';
        } elseif ($table !== '' && $columnsOption === '') {
            $type = 'table';
        } elseif ($table !== '' && $columnsOption !== '') {
            $type = 'columns';
            $columns = array_values(array_filter(array_map('trim', explode(',', $columnsOption))));

            if ($columns === []) {
                $this->error('Columns list cannot be empty when using --columns.');

                return self::FAILURE;
            }
        } else {
            $this->error('When using --columns you must also provide --table.');

            return self::FAILURE;
        }

        $now = now();

        DB::table('index_advisor_ignores')->insert([
            'type' => $type,
            'fingerprint' => $fingerprint !== '' ? $fingerprint : null,
            'table_name' => $table !== '' ? $table : null,
            'columns' => $columns !== null ? json_encode($columns) : null,
            'reason' => (string) ($this->option('reason') ?? ''),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->info('Ignore rule added.');

        return self::SUCCESS;
    }
}

