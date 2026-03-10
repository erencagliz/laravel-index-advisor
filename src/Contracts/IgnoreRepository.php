<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Contracts;

interface IgnoreRepository
{
    public function shouldIgnoreFingerprint(string $fingerprint): bool;

    public function shouldIgnoreSuggestion(string $table, array $columns, string $fingerprint): bool;
}

