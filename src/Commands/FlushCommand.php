<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class FlushCommand extends Command
{
    protected $signature = 'index-advisor:flush
        {--older-than= : Retention in days}
        {--all : Flush all stored data}
        {--force : Do not ask for confirmation when using --all}';

    protected $description = 'Clean up stored query aggregates and suggestions.';

    public function handle(): int
    {
        if ($this->option('all')) {
            if (! $this->option('force') &&
                ! $this->confirm('This will delete all collected index advisor data. Are you sure?', false)
            ) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }

            DB::table('index_advisor_suggestions')->truncate();
            DB::table('index_advisor_queries')->truncate();
            $this->info('All index advisor data has been flushed.');

            return self::SUCCESS;
        }

        $daysOption = $this->option('older-than');
        $days = $daysOption !== null
            ? (int) $daysOption
            : (int) config('index-advisor.retention_days', 7);

        $cutoff = Carbon::now()->subDays($days);

        $deletedSuggestions = DB::table('index_advisor_suggestions')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $deletedQueries = DB::table('index_advisor_queries')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info(sprintf(
            'Flushed %d suggestions and %d query aggregates older than %d days.',
            $deletedSuggestions,
            $deletedQueries,
            $days
        ));

        return self::SUCCESS;
    }
}

