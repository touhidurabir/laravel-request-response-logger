<?php

namespace Touhidurabir\RequestResponseLogger\Tests\Traits;

use Touhidurabir\RequestResponseLogger\Facades\RequestResponseLogger;
use Touhidurabir\RequestResponseLogger\RequestResponseLoggerServiceProvider;
use Touhidurabir\RequestResponseLogger\Models\RequestResponseLogger as RequestResponseLoggerModel;

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


    /**
     * Define environment setup.
     *
     * @param  Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app) {

        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('app.url', 'http://localhost/');
        $app['config']->set('app.debug', false);
        $app['config']->set('app.key', env('APP_KEY', 'base64:4vaRSWQcoaHh8mA8qIRxL1Ei+UNIj9Wst9rY+ne2rE4='));
        $app['config']->set('app.cipher', 'AES-256-CBC');
        
        $app['config']->set('request-response-logger', [
            'table'                         => 'request_response_loggers',
            'model'                         => RequestResponseLoggerModel::class,
            'log_on_queue'                  => false,
            'jobs'                          => [
                'log'   => \Touhidurabir\RequestResponseLogger\Jobs\StoreRequestResponse::class,
                'clear' => \Touhidurabir\RequestResponseLogger\Jobs\DeleteRequestResponse::class,
            ],
            'json_to_array_on_retrieve'     => true,
            'store_on_redis'                => false,
            'max_redis_count'               => 4,
            'redis_store_in_segment_count'  => 2,
            'redis_key_name'                => 'request_response_log',
            'redis_configs'                 => [
                'url'       => env('REDIS_URL'),
                'host'      => env('REDIS_HOST', '127.0.0.1'),
                'password'  => env('REDIS_PASSWORD', null),
                'port'      => env('REDIS_PORT', '6379'),
                'database'  => env('REDIS_DB', '0'),
            ],
            'fallback_on_redis_failure'     => true,
            'delete_in_segment_count'       => 2,
        ]);
    }
    
}