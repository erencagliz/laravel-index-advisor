<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Jobs;

use Erencagliz\LaravelIndexAdvisor\Contracts\QueryStore;
use Erencagliz\LaravelIndexAdvisor\DTO\ObservedQuery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RecordQueryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public $backoff = [2, 5, 10];

    public function __construct(
        public readonly ObservedQuery $observedQuery
    ) {
    }

    public function handle(QueryStore $store): void
    {
        $store->record($this->observedQuery);
    }
}
