<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor;

use Erencagliz\LaravelIndexAdvisor\Contracts\IgnoreRepository;
use Erencagliz\LaravelIndexAdvisor\Contracts\QueryStore;
use Erencagliz\LaravelIndexAdvisor\Contracts\SchemaIndexRepository;
use Erencagliz\LaravelIndexAdvisor\Contracts\SuggestionEngine;
use Erencagliz\LaravelIndexAdvisor\IndexAdvisor;
use Erencagliz\LaravelIndexAdvisor\Listeners\QueryWatcher;
use Erencagliz\LaravelIndexAdvisor\Parsers\QueryNormalizer;
use Erencagliz\LaravelIndexAdvisor\Parsers\SqlAstParser;
use Erencagliz\LaravelIndexAdvisor\Parsers\AstQueryShapeBuilder;
use Erencagliz\LaravelIndexAdvisor\Repositories\DatabaseIgnoreRepository;
use Erencagliz\LaravelIndexAdvisor\Repositories\DatabaseQueryStore;
use Erencagliz\LaravelIndexAdvisor\Repositories\DefaultSchemaIndexRepository;
use Erencagliz\LaravelIndexAdvisor\Services\DefaultSuggestionEngine;
use Erencagliz\LaravelIndexAdvisor\Services\ExplainAnalyzer;
use Erencagliz\LaravelIndexAdvisor\Services\WorkloadAnalyzer;
use Erencagliz\LaravelIndexAdvisor\Services\QueryFingerprint;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class LaravelIndexAdvisorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/index-advisor.php',
            'index-advisor'
        );
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->publishMigrations();
        $this->registerCommands();
        $this->registerBindings();
        $this->registerQueryListener();
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/index-advisor.php' => config_path('index-advisor.php'),
        ], 'index-advisor-config');
    }

    private function publishMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'index-advisor-migrations');
    }

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Commands\ReportCommand::class,
            Commands\SuggestCommand::class,
            Commands\AnalyzeCommand::class,
            Commands\FlushCommand::class,
            Commands\GenerateMigrationCommand::class,
            Commands\MarkSuggestionCommand::class,
            Commands\IgnoreCommand::class,
            Commands\WorkloadReportCommand::class,
        ]);
    }

    private function registerBindings(): void
    {
        $this->app->singleton(QueryStore::class, DatabaseQueryStore::class);
        $this->app->singleton(IgnoreRepository::class, DatabaseIgnoreRepository::class);
        $this->app->singleton(SchemaIndexRepository::class, DefaultSchemaIndexRepository::class);
        $this->app->singleton(QueryNormalizer::class);
        $this->app->singleton(SqlAstParser::class);
        $this->app->singleton(AstQueryShapeBuilder::class);
        $this->app->singleton(QueryFingerprint::class);
        $this->app->singleton(ExplainAnalyzer::class);
        $this->app->singleton(WorkloadAnalyzer::class);
        $this->app->singleton(SuggestionEngine::class, DefaultSuggestionEngine::class);
        $this->app->singleton('index-advisor', IndexAdvisor::class);
        $this->app->singleton(QueryWatcher::class, function ($app): QueryWatcher {
            return new QueryWatcher(
                store: $app->make(QueryStore::class),
                normalizer: $app->make(QueryNormalizer::class),
                fingerprint: $app->make(QueryFingerprint::class),
            );
        });
    }

    private function registerQueryListener(): void
    {
        if (! config('index-advisor.enabled', true)) {
            return;
        }

        Event::listen(QueryExecuted::class, function (QueryExecuted $event): void {
            /** @var QueryWatcher $watcher */
            $watcher = $this->app->make(QueryWatcher::class);
            $watcher->handle($event);
        });
    }
}

