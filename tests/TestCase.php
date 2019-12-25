<?php

namespace Tests;

use Elephant\Contracts\Mail\Kernel;
use Elephant\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        if (! file_exists(__DIR__ . '/bootstrap/cache')) {
            mkdir(__DIR__.'/bootstrap/cache', 0777, true);
        }
        $this->app = new \Elephant\Foundation\Application(__DIR__);

        $this->app->singleton(
            Kernel::class,
            \Elephant\Foundation\Mail\Kernel::class
        );

        $this->app->make(Kernel::class)->bootstrap();

        return $this->app;
    }
}
