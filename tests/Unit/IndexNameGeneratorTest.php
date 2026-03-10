<?php

declare(strict_types=1);

use Erencagliz\LaravelIndexAdvisor\Services\IndexNameGenerator;

it('generates index names within length limits', function (): void {
    $generator = new IndexNameGenerator();

    $name = $generator->generate('orders', ['tenant_id', 'status', 'created_at'], 'index', 32);

    expect(strlen($name))->toBeLessThanOrEqual(32);
});

it('appends hash suffix when truncating long names', function (): void {
    $generator = new IndexNameGenerator();

    $name = $generator->generate(
        'very_long_orders_table_name_that_needs_truncation',
        ['very_long_tenant_id_column_name', 'another_very_long_status_column_name'],
        'index',
        32
    );

    expect(strlen($name))->toBeLessThanOrEqual(32)
        ->and(str_contains($name, '_'))->toBeTrue();
});

