<?php

namespace Touhidurabir\RequestResponseLogger\Tests;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Schema;
use Touhidurabir\RequestResponseLogger\Tests\Traits\LaravelTestBootstrapping;

class DatabaseTest extends TestCase
{
    use LaravelTestBootstrapping;

    /**
     * @test
     */
    #[Test]
    public function the_logger_table_in_config_will_run_via_migration()
    {

        include_once(__DIR__ . '/../database/migrations/create_request_response_loggers_table.php.stub');

        $this->assertNull((new \CreateRequestResponseLoggersTable())->up());

        $this->assertTrue(Schema::hasTable(config('request-response-logger.table')));
    }


    /**
     * @test
     */
    #[Test]
    public function the_logger_table_can_be_rolled_back()
    {

        include_once(__DIR__ . '/../database/migrations/create_request_response_loggers_table.php.stub');

        (new \CreateRequestResponseLoggersTable())->up();

        $this->assertNull((new \CreateRequestResponseLoggersTable())->down());

        $this->assertFalse(Schema::hasTable(config('request-response-logger.table')));
    }


    /**
     * @test
     */
    #[Test]
    public function the_migration_will_throw_exception_if_defined_config_table_already_exists()
    {

        include_once(__DIR__ . '/../database/migrations/create_request_response_loggers_table.php.stub');

        (new \CreateRequestResponseLoggersTable())->up();

        $this->expectException(Exception::class);

        (new \CreateRequestResponseLoggersTable())->up();
    }


    /**
     * @test
     */
    #[Test]
    public function the_migration_exception_will_name_the_existing_table()
    {

        include_once(__DIR__ . '/../database/migrations/create_request_response_loggers_table.php.stub');

        (new \CreateRequestResponseLoggersTable())->up();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('a table of name request_response_loggers already exists');

        (new \CreateRequestResponseLoggersTable())->up();
    }


    /**
     * @test
     */
    #[Test]
    public function the_migration_will_throw_exception_if_the_config_table_is_not_defined()
    {

        include_once(__DIR__ . '/../database/migrations/create_request_response_loggers_table.php.stub');

        config(['request-response-logger.table' => '']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('config/request-response-logger.php');

        (new \CreateRequestResponseLoggersTable())->up();
    }

}
