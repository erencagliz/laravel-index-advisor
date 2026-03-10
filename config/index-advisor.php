<?php

declare(strict_types=1);

return [
    'enabled' => env('INDEX_ADVISOR_ENABLED', true),

    'sample_rate' => (float) env('INDEX_ADVISOR_SAMPLE_RATE', 1.0),

    'min_query_time_ms' => (int) env('INDEX_ADVISOR_MIN_QUERY_TIME_MS', 20),

    'min_executions' => (int) env('INDEX_ADVISOR_MIN_EXECUTIONS', 25),

    'retention_days' => (int) env('INDEX_ADVISOR_RETENTION_DAYS', 7),

    'store_raw_sql_sample' => env('INDEX_ADVISOR_STORE_RAW_SQL_SAMPLE', false),

    'ignore_connections' => [
        'sqlite_testing',
    ],

    'ignore_tables' => [
        'migrations',
        'jobs',
        'cache',
        'sessions',
        'failed_jobs',
        'index_advisor_queries',
        'index_advisor_suggestions',
    ],

    'ignore_paths' => [
        'horizon*',
        'telescope*',
    ],

    'explain' => [
        'enabled' => env('INDEX_ADVISOR_EXPLAIN_ENABLED', false),
        'sample_limit' => 5,
    ],

    'queue' => [
        'enabled' => false,
        'connection' => null,
        'queue' => null,
    ],
];

