<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sanchescom\Rest\RestServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [RestServiceProvider::class];
    }
}
