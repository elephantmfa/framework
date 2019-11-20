<?php

namespace Elephant\Async;

use Elephant\Async\Filesystem\AsyncFilesystem;
use Illuminate\Support\ServiceProvider;

class AsyncServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app['filesystem']->extend('async', function ($app, $config) {
            return new AsyncFilesystem($app, $config);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
