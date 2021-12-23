<?php

namespace Touhidurabir\RequestResponseLogger\Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;
use Touhidurabir\RequestResponseLogger\Tests\App\RedisFaker;
use Touhidurabir\RequestResponseLogger\Models\RequestResponseLogger;
use Touhidurabir\RequestResponseLogger\Middlewares\LogRequestResponse;
use Touhidurabir\RequestResponseLogger\Tests\Traits\LaravelTestBootstrapping;
use Touhidurabir\RequestResponseLogger\Tests\Traits\TestDatabaseBootstraping;

class RedisTest extends TestCase {
    
    use LaravelTestBootstrapping;

    use TestDatabaseBootstraping;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void {

        parent::setUp();

        // https://laracasts.com/discuss/channels/testing/how-can-i-test-a-custom-package-that-use-facades?page=1

        $this->app->singleton('redis', function ($app) {

            $config = $app->make('config')->get('request-response-logger.redis_configs', []);

            return new RedisFaker($config);
        });

        $this->app->bind('redis.connection', function ($app) {

            return $app['redis']->connection();
        });

        Config::set('request-response-logger.store_on_redis', true);
        Config::set('request-response-logger.fallback_on_redis_failure', false);
    }

    
    /**
     * Define routes setup.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    protected function defineRoutes($router) {
        
        Route::any('/request-response-logger-test', function() {
            
            return response()->json([
                'status'    => true,
                'data'      => request()->all(),
            ]);

        })->middleware(['web', LogRequestResponse::class]);
    }


    /**
     * @test
     */
    public function middleware_saves_the_data_redis_storage() {

        $request = $this->get('/request-response-logger-test');
        
        $this->assertEquals(1, Redis::llen(config('request-response-logger.redis_key_name')));

        $request = $this->get('/request-response-logger-test');

        $this->assertEquals(2, Redis::llen(config('request-response-logger.redis_key_name')));
    }


    /**
     * @test
     */
    public function middleware_will_not_saves_the_data_in_model_table_first() {

        $request = $this->get('/request-response-logger-test');
        
        $this->assertEquals(1, Redis::llen(config('request-response-logger.redis_key_name')));
        $this->assertEquals(RequestResponseLogger::count(), 0);

        $request = $this->get('/request-response-logger-test');

        $this->assertEquals(2, Redis::llen(config('request-response-logger.redis_key_name')));
        $this->assertEquals(RequestResponseLogger::count(), 0);
    }


    /**
     * @test
     */
    public function middleware_will_push_the_data_in_model_table_from_redis_storage_when_hits_max_store_limit() {
        
        for ($i=0; $i < config('request-response-logger.max_redis_count') ; $i++) { 
            
            $this->get('/request-response-logger-test');
        }

        $this->assertEquals(0, Redis::llen(config('request-response-logger.redis_key_name')));

        $this->assertEquals(RequestResponseLogger::count(), config('request-response-logger.max_redis_count'));
    }

    
}