<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Services;

final class IndexNameGenerator
{
    public function generate(string $table, array $columns, string $type = 'index', int $maxLength = 64): string
    {
        $base = $table . '_' . implode('_', $columns) . '_' . substr($type, 0, 3);

        if (strlen($base) <= $maxLength) {
            return $base;
        }

        $hash = substr(sha1($base), 0, 8);

        $prefixMax = $maxLength - 1 - strlen($hash);

        $prefix = substr($base, 0, $prefixMax);

        return $prefix . '_' . $hash;
    }
}

