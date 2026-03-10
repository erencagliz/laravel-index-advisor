<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Parsers;

final class QueryNormalizer
{
    public function normalize(string $sql): string
    {
        // Placeholders: numeric and string literals basic replacement
        $normalized = preg_replace(
            [
                "/'(?:''|[^'])*'/u", // quoted strings
                '/\b\d+\b/u',        // integers
            ],
            ['?', '?'],
            $sql
        ) ?? $sql;

        // Remove backticks / double quotes around identifiers where safe
        $normalized = str_replace(['`', '"'], '', $normalized);

        // Normalize whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        // Trim and lowercase
        $normalized = trim($normalized);
        $normalized = strtolower($normalized);

        return $normalized;
    }
}

