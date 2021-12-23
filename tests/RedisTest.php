<?php

namespace Touhidurabir\RequestResponseLogger\Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Tester\CommandTester;
use Touhidurabir\RequestResponseLogger\Tests\App\RedisFaker;
use Touhidurabir\RequestResponseLogger\Models\RequestResponseLogger;
use Touhidurabir\RequestResponseLogger\Middlewares\LogRequestResponse;
use Touhidurabir\RequestResponseLogger\Tests\Traits\LaravelTestBootstrapping;
use Touhidurabir\RequestResponseLogger\Tests\Traits\TestDatabaseBootstraping;
use Touhidurabir\RequestResponseLogger\Console\RequestResponseLoggerRedisImport;

class RedisTest extends TestCase {
    
    use LaravelTestBootstrapping;

    use TestDatabaseBootstraping;

    /**
     * The testable dummy command
     *
     * @var object<\Symfony\Component\Console\Tester\CommandTester>
     */
    protected $redisImportCommand;


    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void {

        parent::setUp();

        // $app = new Container();

        $this->app->singleton('redis', function ($app) {

            $config = $app->make('config')->get('request-response-logger.redis_configs', []);

            return new RedisFaker($config);
        });

        $this->app->bind('redis.connection', function ($app) {

            return $app['redis']->connection();
        });

        $this->redisImportCommand = $this->configureTestCommand(RequestResponseLoggerRedisImport::class);

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
     * Configure the testable command as dummy command
     * 
     * @param  string $commandClass
     * @return object<\Symfony\Component\Console\Tester\CommandTester>
     */
    protected function configureTestCommand(string $commandClass) {

        $command = new $commandClass;
        $command->setLaravel($this->app);

        return new CommandTester($command);
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


    /**
     * @test
     */
    public function the_redis_import_command_will_run() {

        $this->assertEquals($this->redisImportCommand->execute([]), 0);
    }


    /**
     * @test
     */
    public function the_redis_import_command_will_import_data_from_redis_storage_to_db() {

        $this->get('/request-response-logger-test');
        $this->get('/request-response-logger-test');

        $this->assertEquals(RequestResponseLogger::count(), 0);
        $this->assertEquals(2, Redis::llen(config('request-response-logger.redis_key_name')));

        $this->redisImportCommand->execute([]);

        $this->assertEquals(0, Redis::llen(config('request-response-logger.redis_key_name')));
        $this->assertEquals(RequestResponseLogger::count(), 2);
    }

}