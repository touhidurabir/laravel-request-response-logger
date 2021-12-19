<?php

namespace Touhidurabir\RequestResponseLogger;

use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Touhidurabir\RequestResponseLogger\RequestResponseLogManager;
use Touhidurabir\RequestResponseLogger\Console\RequestResponseLogCleaner;
use Touhidurabir\RequestResponseLogger\Console\RequestResponseLoggerExporter;

class RequestResponseLoggerServiceProvider extends ServiceProvider {
    
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot() {

        if ( $this->app->runningInConsole() ) {

            $this->commands([
                RequestResponseLogCleaner::class,
                RequestResponseLoggerExporter::class,
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
    public function register() {

        $this->mergeConfigFrom(
            __DIR__.'/../config/request-response-logger.php', 'request-response-logger'
        );

        $this->app->bind('request-response-logger', function ($app) {
            return new RequestResponseLogManager;
        });
    }


    /**
     * Returns existing migration file if found, else uses the current timestamp.
     *
     * @param  string $migrationFileName
     * @return string
     */
    protected function getMigrationFileName($migrationFileName): string {
        
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