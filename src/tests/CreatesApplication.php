<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        if ($app->configurationIsCached()) {
            throw new \RuntimeException('Config is cached, run `php artisan config:clear` before tests.');
        }

        if ($app['config']->get('database.connections.mysql.database') !== 'laravel_testing') {
            throw new \RuntimeException('Tests must run on laravel_testing database.');
        }

        return $app;
    }
}
