<?php

declare(strict_types=1);

use Erencagliz\LaravelIndexAdvisor\DTO\IndexSuggestion;
use Erencagliz\LaravelIndexAdvisor\Services\MigrationStubGenerator;

it('generates migration stub with matching up and down index names', function (): void {
    $suggestion = new IndexSuggestion(
        table: 'orders',
        columns: ['tenant_id', 'status'],
        indexType: 'index',
        reason: 'test',
        confidenceScore: 80,
        fingerprint: 'abcdef123456',
        supportingStats: [],
        existingSimilarIndexes: [],
        warnings: [],
    );

    $generator = new MigrationStubGenerator();

    $stub = $generator->buildMigrationClass($suggestion);

    expect($stub)
        ->toContain("\$table->index(array (")
        ->toContain("'tenant_id'")
        ->toContain("'status'")
        ->toContain("abcdef123456_idx")
        ->toContain("->dropIndex('abcdef123456_idx')");
});

