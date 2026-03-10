<?php

declare(strict_types=1);

use Orchestra\Testbench\TestCase;
use Erencagliz\LaravelIndexAdvisor\LaravelIndexAdvisorServiceProvider;

class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelIndexAdvisorServiceProvider::class,
        ];
    }

    public function test_config_is_merged_and_publishable(): void
    {
        $this->assertTrue(config('index-advisor.enabled'));
        $this->assertArrayHasKey('ignore_tables', config('index-advisor'));
    }
}

