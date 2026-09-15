<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests;

use Illuminate\Pagination\Paginator;
use Orchestra\Testbench\TestCase as Orchestra;
use Sanchescom\Rest\RestServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [RestServiceProvider::class];
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Reset paginator resolvers to prevent leak to subsequent test suites
        Paginator::currentPathResolver(fn () => '/');
        Paginator::currentPageResolver(fn () => 1);
    }
}
