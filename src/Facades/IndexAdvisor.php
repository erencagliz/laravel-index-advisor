<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Facades;

use Illuminate\Support\Facades\Facade;

final class IndexAdvisor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'index-advisor';
    }
}

