<?php

namespace Touhidurabir\RequestResponseLogger\Tests\Traits;

trait TestDatabaseBootstraping {

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations() {

        include_once(__DIR__ . '/../../database/migrations/create_request_response_loggers_table.php.stub');

        $this->loadMigrationsFrom(__DIR__ . '/../App/database/migrations');
        
        $this->artisan('migrate', ['--database' => 'testbench'])->run();

        (new \CreateRequestResponseLoggersTable)->up();

        $this->beforeApplicationDestroyed(function () {
            $this->artisan('migrate:rollback', ['--database' => 'testbench'])->run();
        });
    }
}