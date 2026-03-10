<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Repositories;

use Erencagliz\LaravelIndexAdvisor\Contracts\SchemaIndexRepository;
use Illuminate\Support\Facades\DB;

final class DefaultSchemaIndexRepository implements SchemaIndexRepository
{
    public function getIndexesForTable(string $connection, string $table): array
    {
        $schemaManager = DB::connection($connection)->getDoctrineSchemaManager();

        $indexes = $schemaManager->listTableIndexes($table);

        $result = [];

        foreach ($indexes as $index) {
            $result[] = [
                'name' => $index->getName(),
                'columns' => $index->getColumns(),
                'unique' => $index->isUnique(),
                'primary' => $index->isPrimary(),
            ];
        }

        return $result;
    }

    public function hasExactIndex(string $connection, string $table, array $columns): bool
    {
        $columns = array_values($columns);

        foreach ($this->getIndexesForTable($connection, $table) as $index) {
            if ($index['columns'] === $columns) {
                return true;
            }
        }

        return false;
    }

    public function findSimilarIndexes(string $connection, string $table, array $columns): array
    {
        $columns = array_values($columns);
        $similar = [];

        foreach ($this->getIndexesForTable($connection, $table) as $index) {
            $indexColumns = array_values($index['columns']);

            if ($this->startsWith($indexColumns, $columns) || $this->startsWith($columns, $indexColumns)) {
                $similar[] = $index;
            }
        }

        return $similar;
    }

    /**
     * @param string[] $haystack
     * @param string[] $needle
     */
    private function startsWith(array $haystack, array $needle): bool
    {
        if (count($needle) === 0 || count($haystack) === 0) {
            return false;
        }

        if (count($needle) > count($haystack)) {
            return false;
        }

        foreach ($needle as $i => $column) {
            if ($haystack[$i] !== $column) {
                return false;
            }
        }

        return true;
    }
}

