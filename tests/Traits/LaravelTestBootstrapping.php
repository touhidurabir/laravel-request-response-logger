<?php

namespace Touhidurabir\RequestResponseLogger\Tests\Traits;

use Touhidurabir\RequestResponseLogger\Facades\RequestResponseLogger;
use Touhidurabir\RequestResponseLogger\RequestResponseLoggerServiceProvider;

trait LaravelTestBootstrapping {

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app) {

        return [
            RequestResponseLoggerServiceProvider::class,
        ];
    }
    

    /**
     * Override application aliases.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageAliases($app) {
        
        return [
            'RequestResponseLogger' => RequestResponseLogger::class,
        ];
    }
}