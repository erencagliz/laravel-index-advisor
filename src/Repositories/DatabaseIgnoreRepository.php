<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Repositories;

use Erencagliz\LaravelIndexAdvisor\Contracts\IgnoreRepository;
use Illuminate\Support\Facades\DB;

final class DatabaseIgnoreRepository implements IgnoreRepository
{
    public function shouldIgnoreFingerprint(string $fingerprint): bool
    {
        return DB::table('index_advisor_ignores')
            ->where('type', 'fingerprint')
            ->where('fingerprint', $fingerprint)
            ->exists();
    }

    public function shouldIgnoreSuggestion(string $table, array $columns, string $fingerprint): bool
    {
        if ($this->shouldIgnoreFingerprint($fingerprint)) {
            return true;
        }

        $hasTableIgnore = DB::table('index_advisor_ignores')
            ->where('type', 'table')
            ->where('table_name', $table)
            ->exists();

        if ($hasTableIgnore) {
            return true;
        }

        $columnsJson = json_encode(array_values($columns), JSON_THROW_ON_ERROR);

        $hasColumnsIgnore = DB::table('index_advisor_ignores')
            ->where('type', 'columns')
            ->where('table_name', $table)
            ->where('columns', $columnsJson)
            ->exists();

        return $hasColumnsIgnore;
    }
}

