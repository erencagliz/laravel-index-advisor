<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class MarkSuggestionCommand extends Command
{
    protected $signature = 'index-advisor:mark
        {suggestion : ID of the suggestion}
        {status : accepted|dismissed}
        {--reason= : Optional reason when dismissing}';

    protected $description = 'Mark an index suggestion as accepted or dismissed.';

    public function handle(): int
    {
        $id = (int) $this->argument('suggestion');
        $status = strtolower((string) $this->argument('status'));

        if (! in_array($status, ['accepted', 'dismissed'], true)) {
            $this->error('Status must be either "accepted" or "dismissed".');

            return self::FAILURE;
        }

        $suggestion = DB::table('index_advisor_suggestions')->where('id', $id)->first();

        if ($suggestion === null) {
            $this->error('Suggestion not found.');

            return self::FAILURE;
        }

        $now = now();

        $update = [
            'status' => $status,
            'updated_at' => $now,
        ];

        if ($status === 'accepted') {
            $update['accepted_at'] = $now;
            $update['dismissed_at'] = null;
            $update['dismissed_reason'] = null;
        }

        if ($status === 'dismissed') {
            $update['dismissed_at'] = $now;
            $update['dismissed_reason'] = (string) ($this->option('reason') ?? '');
        }

        DB::table('index_advisor_suggestions')->where('id', $id)->update($update);

        $this->info(sprintf('Suggestion #%d marked as %s.', $id, $status));

        return self::SUCCESS;
    }
}

