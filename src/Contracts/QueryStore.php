<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Contracts;

use Erencagliz\LaravelIndexAdvisor\DTO\ObservedQuery;

interface QueryStore
{
    public function record(ObservedQuery $query): void;
}

