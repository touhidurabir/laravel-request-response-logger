<?php

namespace Touhidurabir\RequestResponseLogger\Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Touhidurabir\RequestResponseLogger\Jobs\StoreRequestResponse;
use Touhidurabir\RequestResponseLogger\Jobs\DeleteRequestResponse;
use Touhidurabir\ModelUuid\UuidGenerator\Generator as UuidGenerator;
use Touhidurabir\RequestResponseLogger\Models\RequestResponseLogger;
use Touhidurabir\RequestResponseLogger\Concerns\JobDispatchableMethod;
use Touhidurabir\RequestResponseLogger\Tests\Traits\LaravelTestBootstrapping;

class JobsTest extends TestCase {
    
    use LaravelTestBootstrapping;

    use JobDispatchableMethod;

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations() {

        include_once(__DIR__ . '/../database/migrations/create_request_response_loggers_table.php.stub');

        $this->loadMigrationsFrom(__DIR__ . '/App/database/migrations');
        
        $this->artisan('migrate', ['--database' => 'testbench'])->run();

        (new \CreateRequestResponseLoggersTable)->up();

        $this->beforeApplicationDestroyed(function () {
            $this->artisan('migrate:rollback', ['--database' => 'testbench'])->run();
        });
    }


    /**
     * @test
     */
    public function the_model_store_job_will_run() {

        Bus::fake();

        $uuid = UuidGenerator::uuid4();

        StoreRequestResponse::dispatch(['uuid' => $uuid]);

        Bus::assertDispatched(StoreRequestResponse::class, function ($job) use ($uuid) {
            return $job->data['uuid'] === $uuid;
        });
    }


    /**
     * @test
     */
    public function the_model_store_job_store_data_to_table() {

        $uuid = UuidGenerator::uuid4();

        $dispatchMethod = $this->getDispatchMethod(false);

        StoreRequestResponse::{$dispatchMethod}(['uuid' => $uuid]);

        $this->assertDatabaseHas(config('request-response-logger.table'), [
            'uuid' => $uuid,
        ]);
    }


    /**
     * @test
     */
    public function the_model_delete_job_will_run() {

        Bus::fake();

        $dispatchMethod = $this->getDispatchMethod(false);

        DeleteRequestResponse::{$dispatchMethod}();

        Bus::assertDispatched(DeleteRequestResponse::class, function ($job) {
            return true;
        });
    }


    /**
     * @test
     */
    public function the_model_delete_job_will_delete_records_from_table() {

        $modelClass = config('request-response-logger.model');

        DB::table(config('request-response-logger.table'))
            ->insert([
                ['response_status_code' => '200', 'marked' => false],
                ['response_status_code' => '201', 'marked' => false],
                ['response_status_code' => '401', 'marked' => true],
                ['response_status_code' => '404', 'marked' => true],
            ]);

        $this->assertEquals($modelClass::count(), 4);

        $dispatchMethod = $this->getDispatchMethod(false);

        DeleteRequestResponse::{$dispatchMethod}();

        $this->assertEquals($modelClass::count(), 0);
    }

}