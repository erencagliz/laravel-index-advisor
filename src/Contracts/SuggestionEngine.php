<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Contracts;

use Erencagliz\LaravelIndexAdvisor\DTO\IndexSuggestion;

interface SuggestionEngine
{
    /**
     * @return IndexSuggestion[]
     */
    public function suggestForFingerprint(string $fingerprint): array;
}

