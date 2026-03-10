<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Contracts;

interface SchemaIndexRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getIndexesForTable(string $connection, string $table): array;

    public function hasExactIndex(string $connection, string $table, array $columns): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findSimilarIndexes(string $connection, string $table, array $columns): array;
}

