<?php

namespace Touhidurabir\RequestResponseLogger;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Redis\RedisManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Touhidurabir\RequestResponseLogger\Console\RequestResponseLogCleaner;
use Touhidurabir\RequestResponseLogger\Console\RequestResponseLoggerExporter;
use Touhidurabir\RequestResponseLogger\Console\RequestResponseLoggerRedisImport;

class RequestResponseLoggerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        if ($this->app->runningInConsole()) {

            $this->commands([
                RequestResponseLogCleaner::class,
                RequestResponseLoggerExporter::class,
                RequestResponseLoggerRedisImport::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../config/request-response-logger.php' => base_path('config/request-response-logger.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_request_response_loggers_table.php.stub' => $this->getMigrationFileName('create_request_response_loggers_table.php'),
        ], 'migrations');
    }


    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        $this->mergeConfigFrom(
            __DIR__.'/../config/request-response-logger.php',
            'request-response-logger'
        );

        $this->app->bind('request-response-logger', function ($app) {
            return new RequestResponseLogManager();
        });

        // the redis manager of the dedicated connection defined by the redis_configs array
        $this->app->singleton('request-response-logger.redis', function ($app) {

            $config = $app->make('config')->get('database.redis', []);

            return new RedisManager($app, Arr::pull($config, 'client', 'phpredis'), [
                'options' => $config['options'] ?? [],
                'default' => $app->make('config')->get('request-response-logger.redis_configs'),
            ]);
        });
    }


    /**
     * Returns existing migration file if found, else uses the current timestamp.
     *
     * @param  string $migrationFileName
     * @return string
     */
    protected function getMigrationFileName($migrationFileName): string
    {

        $timestamp = date('Y_m_d_His');

        $filesystem = $this->app->make(Filesystem::class);

        return Collection::make($this->app->databasePath() . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR)
                        ->flatMap(function ($path) use ($filesystem, $migrationFileName) {
                            return $filesystem->glob($path . '*_' . $migrationFileName);
                        })
                        ->push($this->app->databasePath()."/migrations/{$timestamp}_{$migrationFileName}")
                        ->first();
    }

}
