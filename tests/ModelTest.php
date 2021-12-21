<?php

namespace Touhidurabir\RequestResponseLogger\Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Touhidurabir\RequestResponseLogger\Tests\App\Models\User;
use Touhidurabir\RequestResponseLogger\Tests\Traits\LaravelTestBootstrapping;

class ModelTest extends TestCase {
    
    use LaravelTestBootstrapping;

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
    public function it_will_have_access_to_model_vai_config() {

        $this->assertTrue(class_exists(config('request-response-logger.model')));
    }


    /**
     * @test
     */
    public function it_is_a_proper_model_class() {

        $modelClass = config('request-response-logger.model');

        $this->assertIsObject(new $modelClass);
        $this->assertInstanceOf(Model::class, new $modelClass);
    }


    /**
     * @test
     */
    public function it_will_store_data_in_table() {

        $modelClass = config('request-response-logger.model');

        $record = $modelClass::create([]);

        $this->assertDatabaseHas(config('request-response-logger.table'), [
            'id' => $record->id,
        ]);
    }


    /**
     * @test
     */
    public function it_can_filter_records_by_defined_scopes() {

        $modelClass = config('request-response-logger.model');

        DB::table(config('request-response-logger.table'))
            ->insert([
                ['response_status_code' => '200', 'marked' => false],
                ['response_status_code' => '201', 'marked' => false],
                ['response_status_code' => '401', 'marked' => true],
                ['response_status_code' => '404', 'marked' => true],
            ]);
        
        $this->assertEquals($modelClass::count(), 4);
        $this->assertEquals($modelClass::successful()->count(), 2);
        $this->assertEquals($modelClass::failed()->count(), 2);
        $this->assertEquals($modelClass::marked()->count(), 2);
    }


    /**
     * @test
     */
    public function it_can_handle_related_to_auth_user() {

        $user = User::create(['email' => 'testuser@test.com', 'password' => Hash::make('123456')]);

        $this->be($user);

        $modelClass = config('request-response-logger.model');

        $log = $modelClass::create([
            'request_auth_user_id' => $user->id
        ]);

        $this->assertEquals($log->user->id, $user->id);
    }

    /**
     * @test
     */
    public function it_can_cast_columns_on_store_and_retrival_as_defined_in_config() {

        $modelClass = config('request-response-logger.model');

        $log = $modelClass::create([
            'request_headers'   => ['x-header-value' => 'some_value'],
            'request_body'      => ['value' => 'some_value'],
            'response_headers'  => ['x-header-value' => 'some_value'],
            'response_body'     => ['value' => 'some_value'],
        ]);

        $this->assertJson($log->getRawOriginal('request_headers'));
        $this->assertJson($log->getRawOriginal('request_body'));

        $this->assertIsArray($log->response_headers);
        $this->assertIsArray($log->response_body);

        Config::set('request-response-logger.json_to_array_on_retrieve', false);

        $this->assertJson($log->response_headers);
        $this->assertJson($log->response_body);
    }

}