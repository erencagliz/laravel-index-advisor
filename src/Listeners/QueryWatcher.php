<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Listeners;

use Erencagliz\LaravelIndexAdvisor\Contracts\QueryStore;
use Erencagliz\LaravelIndexAdvisor\DTO\ObservedQuery;
use Erencagliz\LaravelIndexAdvisor\Parsers\QueryNormalizer;
use Erencagliz\LaravelIndexAdvisor\Services\QueryFingerprint;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

final class QueryWatcher
{
    public function __construct(
        private readonly QueryStore $store,
        private readonly QueryNormalizer $normalizer,
        private readonly QueryFingerprint $fingerprint,
    ) {
    }

    public function handle(QueryExecuted $event): void
    {
        if (! config('index-advisor.enabled', true)) {
            return;
        }

        if ($this->shouldIgnoreConnection($event->connectionName)) {
            return;
        }

        if (! $this->passesSampling()) {
            return;
        }

        $executionTimeMs = (float) $event->time;
        $minTime = (int) config('index-advisor.min_query_time_ms', 20);

        if ($executionTimeMs < $minTime) {
            return;
        }

        $normalizedSql = $this->normalizer->normalize($event->sql);

        $fingerprint = $this->fingerprint->make(
            $normalizedSql,
            $event->connectionName,
            null
        );

        $observed = new ObservedQuery(
            connectionName: $event->connectionName,
            rawSql: $event->sql,
            normalizedSql: $normalizedSql,
            fingerprint: $fingerprint,
            executionTimeMs: $executionTimeMs,
            bindingsCount: count($event->bindings),
            routeName: Request::route()?->getName(),
            requestPath: Request::path(),
            httpMethod: Request::method(),
            jobClass: null,
            userId: $this->resolveUserId(Auth::user()),
            observedAt: new \DateTimeImmutable(),
        );

        $this->store->record($observed);
    }

    private function shouldIgnoreConnection(string $connection): bool
    {
        $ignored = config('index-advisor.ignore_connections', []);

        return in_array($connection, $ignored, true);
    }

    private function passesSampling(): bool
    {
        $rate = (float) config('index-advisor.sample_rate', 1.0);

        if ($rate >= 1.0) {
            return true;
        }

        if ($rate <= 0.0) {
            return false;
        }

        return mt_rand() / mt_getrandmax() <= $rate;
    }

    private function resolveUserId(?Authenticatable $user): ?int
    {
        if ($user === null) {
            return null;
        }

        $id = $user->getAuthIdentifier();

        return is_int($id) ? $id : null;
    }
}

