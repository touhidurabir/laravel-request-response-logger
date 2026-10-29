<?php

namespace Touhidurabir\RequestResponseLogger\Tests;

use PHPUnit\Framework\Attributes\Test;
use Orchestra\Testbench\TestCase;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Touhidurabir\RequestResponseLogger\RequestResponseLogManager;
use Touhidurabir\RequestResponseLogger\Concerns\JobDispatchableMethod;
use Touhidurabir\RequestResponseLogger\Facades\RequestResponseLogger;
use Touhidurabir\RequestResponseLogger\Tests\Traits\LaravelTestBootstrapping;
use Touhidurabir\RequestResponseLogger\RequestResponseLoggerServiceProvider;

class ServiceProviderTest extends TestCase
{
    use LaravelTestBootstrapping;

    use JobDispatchableMethod;

    /**
     * @test
     */
    #[Test]
    public function the_facade_resolves_the_log_manager()
    {

        $this->assertInstanceOf(RequestResponseLogManager::class, RequestResponseLogger::getFacadeRoot());
    }


    /**
     * @test
     */
    #[Test]
    public function the_commands_are_registered()
    {

        $commands = array_keys(Artisan::all());

        $this->assertContains('request-response-logger:clear', $commands);
        $this->assertContains('request-response-logger:export', $commands);
        $this->assertContains('request-response-logger:redis-import', $commands);
    }


    /**
     * @test
     */
    #[Test]
    public function the_config_and_migration_can_be_published()
    {

        $config = ServiceProvider::pathsToPublish(RequestResponseLoggerServiceProvider::class, 'config');

        $this->assertSame([base_path('config/request-response-logger.php')], array_values($config));
        $this->assertFileExists(array_keys($config)[0]);

        $migrations = ServiceProvider::pathsToPublish(RequestResponseLoggerServiceProvider::class, 'migrations');

        $this->assertFileExists(array_keys($migrations)[0]);
        $this->assertMatchesRegularExpression(
            '#/migrations/\d{4}_\d{2}_\d{2}_\d{6}_create_request_response_loggers_table\.php$#',
            array_values($migrations)[0]
        );
    }


    /**
     * @test
     */
    #[Test]
    public function the_dedicated_redis_manager_uses_the_app_redis_client_and_options()
    {

        config([
            'database.redis.client'                 => 'predis',
            'database.redis.options'                => ['prefix' => 'app_prefix:'],
            'request-response-logger.redis_configs' => ['host' => 'redis.test'],
        ]);

        $manager = $this->app->make('request-response-logger.redis');

        $this->assertInstanceOf(RedisManager::class, $manager);
        $this->assertSame($manager, $this->app->make('request-response-logger.redis'));

        $driver = new \ReflectionProperty($manager, 'driver');
        $driver->setAccessible(true);

        $config = new \ReflectionProperty($manager, 'config');
        $config->setAccessible(true);

        $this->assertSame('predis', $driver->getValue($manager));
        $this->assertSame([
            'options' => ['prefix' => 'app_prefix:'],
            'default' => ['host' => 'redis.test'],
        ], $config->getValue($manager));
    }


    /**
     * @test
     */
    #[Test]
    public function the_published_config_hides_the_credentials_by_default()
    {

        $config = require __DIR__ . '/../config/request-response-logger.php';

        $this->assertSame(['password', 'password_confirmation', 'current_password'], $config['hidden_request_fields']);
        $this->assertSame(
            ['authorization', 'cookie', 'set-cookie', 'php-auth-pw', 'x-csrf-token', 'x-xsrf-token'],
            $config['hidden_headers']
        );
    }


    /**
     * @test
     */
    #[Test]
    public function the_dispatch_method_follows_the_queue_setting()
    {

        $this->assertSame('dispatch', $this->getDispatchMethod(true));
        $this->assertSame('dispatchSync', $this->getDispatchMethod(false));
    }

}
