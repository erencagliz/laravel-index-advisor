<?php

declare(strict_types=1);

use Erencagliz\LaravelIndexAdvisor\Contracts\SchemaIndexRepository;
use Erencagliz\LaravelIndexAdvisor\LaravelIndexAdvisorServiceProvider;
use Erencagliz\LaravelIndexAdvisor\Services\DefaultSuggestionEngine;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase;

class DefaultSuggestionEngineTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelIndexAdvisorServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
    }

    public function test_single_column_equality_suggests_index(): void
    {
        DB::table('index_advisor_queries')->insert([
            'fingerprint' => 'fp_single',
            'connection_name' => 'mysql',
            'table_name' => 'orders',
            'normalized_sql' => 'select * from orders where user_id = ? limit ?',
            'sample_raw_sql' => null,
            'executions' => 500,
            'total_time_ms' => 5000,
            'avg_time_ms' => 10,
            'max_time_ms' => 50,
            'p95_time_ms' => 20,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'parse_status' => 'parsed',
            'parse_warnings' => null,
            'shape' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fakeSchema = new class implements SchemaIndexRepository {
            public function getIndexesForTable(string $connection, string $table): array
            {
                return [];
            }

            public function hasExactIndex(string $connection, string $table, array $columns): bool
            {
                return false;
            }

            public function findSimilarIndexes(string $connection, string $table, array $columns): array
            {
                return [];
            }
        };

        $this->app->instance(SchemaIndexRepository::class, $fakeSchema);

        /** @var DefaultSuggestionEngine $engine */
        $engine = $this->app->make(DefaultSuggestionEngine::class);

        $suggestions = $engine->suggestForFingerprint('fp_single');

        $this->assertCount(1, $suggestions);
        $this->assertSame(['user_id'], $suggestions[0]->columns);
        $this->assertGreaterThanOrEqual(60, $suggestions[0]->confidenceScore);
    }

    public function test_multi_column_equality_with_order_by_suggests_composite_index(): void
    {
        DB::table('index_advisor_queries')->insert([
            'fingerprint' => 'fp_multi',
            'connection_name' => 'mysql',
            'table_name' => 'orders',
            'normalized_sql' => 'select * from orders where tenant_id = ? and status = ? order by created_at desc limit ?',
            'sample_raw_sql' => null,
            'executions' => 300,
            'total_time_ms' => 9000,
            'avg_time_ms' => 30,
            'max_time_ms' => 120,
            'p95_time_ms' => 80,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'parse_status' => 'parsed',
            'parse_warnings' => null,
            'shape' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fakeSchema = new class implements SchemaIndexRepository {
            public function getIndexesForTable(string $connection, string $table): array
            {
                return [];
            }

            public function hasExactIndex(string $connection, string $table, array $columns): bool
            {
                return false;
            }

            public function findSimilarIndexes(string $connection, string $table, array $columns): array
            {
                return [];
            }
        };

        $this->app->instance(SchemaIndexRepository::class, $fakeSchema);

        /** @var DefaultSuggestionEngine $engine */
        $engine = $this->app->make(DefaultSuggestionEngine::class);

        $suggestions = $engine->suggestForFingerprint('fp_multi');

        $this->assertNotEmpty($suggestions);
        $columns = $suggestions[0]->columns;
        $this->assertSame(['tenant_id', 'status', 'created_at'], $columns);
    }

    public function test_similar_existing_index_lowers_confidence(): void
    {
        DB::table('index_advisor_queries')->insert([
            'fingerprint' => 'fp_similar',
            'connection_name' => 'mysql',
            'table_name' => 'orders',
            'normalized_sql' => 'select * from orders where tenant_id = ? and status = ?',
            'sample_raw_sql' => null,
            'executions' => 1000,
            'total_time_ms' => 60000,
            'avg_time_ms' => 60,
            'max_time_ms' => 200,
            'p95_time_ms' => 150,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'parse_status' => 'parsed',
            'parse_warnings' => null,
            'shape' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fakeSchema = new class implements SchemaIndexRepository {
            public function getIndexesForTable(string $connection, string $table): array
            {
                return [
                    [
                        'name' => 'orders_tenant_id_idx',
                        'columns' => ['tenant_id'],
                        'unique' => false,
                        'primary' => false,
                    ],
                ];
            }

            public function hasExactIndex(string $connection, string $table, array $columns): bool
            {
                return false;
            }

            public function findSimilarIndexes(string $connection, string $table, array $columns): array
            {
                return [
                    [
                        'name' => 'orders_tenant_id_idx',
                        'columns' => ['tenant_id'],
                        'unique' => false,
                        'primary' => false,
                    ],
                ];
            }
        };

        $this->app->instance(SchemaIndexRepository::class, $fakeSchema);

        /** @var DefaultSuggestionEngine $engine */
        $engine = $this->app->make(DefaultSuggestionEngine::class);

        $suggestions = $engine->suggestForFingerprint('fp_similar');

        $this->assertNotEmpty($suggestions);
        $this->assertLessThan(90, $suggestions[0]->confidenceScore);
        $this->assertGreaterThanOrEqual(40, $suggestions[0]->confidenceScore);
    }
}

