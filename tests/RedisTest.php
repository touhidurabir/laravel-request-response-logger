<?php

namespace Touhidurabir\RequestResponseLogger\Tests;

use PHPUnit\Framework\Attributes\Test;
use Orchestra\Testbench\TestCase;
use Mockery;
use Illuminate\Contracts\Redis\Connector;
use Illuminate\Redis\Connections\Connection;
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

class RedisTest extends TestCase
{
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
    protected function setUp(): void
    {

        parent::setUp();

        // $app = new Container();

        $this->app->singleton('redis', function ($app) {

            return new RedisFaker();
        });

        $this->app->bind('redis.connection', function ($app) {

            return $app['redis']->connection();
        });

        $this->redisImportCommand = $this->configureTestCommand(RequestResponseLoggerRedisImport::class);

        Config::set('request-response-logger.store_on_redis', true);
        Config::set('request-response-logger.fallback_on_redis_failure', false);

        // use the default redis connection, which is the RedisFaker
        Config::set('request-response-logger.redis_configs', null);
    }


    /**
     * Fake the connector of the package's dedicated redis manager and return
     * the redis connection mock it connects to
     *
     * @param  array $connectedWith
     * @return \Mockery\MockInterface
     */
    protected function fakeDedicatedRedisConnection(array &$connectedWith)
    {

        $connection = Mockery::mock(Connection::class)->shouldIgnoreMissing();

        $connector = Mockery::mock(Connector::class);
        $connector->shouldReceive('connect')->andReturnUsing(function ($config) use ($connection, &$connectedWith) {

            $connectedWith = $config;

            return $connection;
        });

        $this->app->make('request-response-logger.redis')
            ->extend(config('database.redis.client', 'phpredis'), function () use ($connector) {
                return $connector;
            });

        return $connection;
    }


    /**
     * Define routes setup.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    protected function defineRoutes($router)
    {

        Route::any('/request-response-logger-test', function () {

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
    protected function configureTestCommand(string $commandClass)
    {

        $command = new $commandClass();
        $command->setLaravel($this->app);

        return new CommandTester($command);
    }


    /**
     * @test
     */
    #[Test]
    public function middleware_saves_the_data_redis_storage()
    {

        $request = $this->get('/request-response-logger-test');

        $this->assertEquals(1, Redis::llen(config('request-response-logger.redis_key_name')));

        $request = $this->get('/request-response-logger-test');

        $this->assertEquals(2, Redis::llen(config('request-response-logger.redis_key_name')));
    }


    /**
     * @test
     */
    #[Test]
    public function middleware_will_not_saves_the_data_in_model_table_first()
    {

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
    #[Test]
    public function middleware_will_push_the_data_in_model_table_from_redis_storage_when_hits_max_store_limit()
    {

        for ($i = 0; $i < config('request-response-logger.max_redis_count') ; $i++) {

            $this->get('/request-response-logger-test');
        }

        $this->assertEquals(0, Redis::llen(config('request-response-logger.redis_key_name')));

        $this->assertEquals(RequestResponseLogger::count(), config('request-response-logger.max_redis_count'));
    }


    /**
     * @test
     */
    #[Test]
    public function the_redis_import_command_will_run()
    {

        $this->assertEquals($this->redisImportCommand->execute([]), 0);
    }


    /**
     * @test
     */
    #[Test]
    public function the_redis_import_command_will_import_data_from_redis_storage_to_db()
    {

        $this->get('/request-response-logger-test');
        $this->get('/request-response-logger-test');

        $this->assertEquals(RequestResponseLogger::count(), 0);
        $this->assertEquals(2, Redis::llen(config('request-response-logger.redis_key_name')));

        $this->redisImportCommand->execute([]);

        $this->assertEquals(0, Redis::llen(config('request-response-logger.redis_key_name')));
        $this->assertEquals(RequestResponseLogger::count(), 2);
    }


    /**
     * @test
     */
    #[Test]
    public function the_redis_configs_can_name_a_redis_connection()
    {

        Config::set('request-response-logger.redis_configs', 'logs');

        $this->get('/request-response-logger-test');

        $this->assertContains('logs', $this->app['redis']->connectionNames);
        $this->assertEquals(1, Redis::llen(config('request-response-logger.redis_key_name')));
    }


    /**
     * @test
     */
    #[Test]
    public function the_redis_configs_array_is_used_as_a_dedicated_connection()
    {

        Config::set('request-response-logger.redis_configs', [
            'host'      => 'redis.test',
            'port'      => '6380',
            'database'  => '3',
        ]);

        $connectedWith = [];

        $connection = $this->fakeDedicatedRedisConnection($connectedWith);
        $connection->shouldReceive('rpush')->once()->andReturn(1);
        $connection->shouldReceive('llen')->andReturn(1);

        $this->get('/request-response-logger-test');

        $this->assertSame('redis.test', $connectedWith['host']);
        $this->assertSame('6380', $connectedWith['port']);
        $this->assertSame('3', $connectedWith['database']);

        // nothing went to the app's own redis connection
        $this->assertEquals(0, Redis::llen(config('request-response-logger.redis_key_name')));
    }


    /**
     * @test
     */
    #[Test]
    public function the_redis_import_command_uses_the_dedicated_connection()
    {

        Config::set('request-response-logger.redis_configs', ['host' => 'redis.test']);

        $connectedWith = [];

        $connection = $this->fakeDedicatedRedisConnection($connectedWith);
        $connection->shouldReceive('llen')->once()->andReturn(0);

        $this->assertEquals(0, $this->redisImportCommand->execute([]));
        $this->assertSame('redis.test', $connectedWith['host']);
    }

}
