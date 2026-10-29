<?php

namespace Touhidurabir\RequestResponseLogger\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Touhidurabir\RequestResponseLogger\Tests\App\Models\User;
use Touhidurabir\RequestResponseLogger\Tests\Traits\LaravelTestBootstrapping;
use Touhidurabir\RequestResponseLogger\Tests\Traits\TestDatabaseBootstraping;

class ModelTest extends TestCase
{
    use LaravelTestBootstrapping;

    use TestDatabaseBootstraping;

    /**
     * @test
     */
    #[Test]
    public function it_will_have_access_to_model_vai_config()
    {

        $this->assertTrue(class_exists(config('request-response-logger.model')));
    }


    /**
     * @test
     */
    #[Test]
    public function it_is_a_proper_model_class()
    {

        $modelClass = config('request-response-logger.model');

        $this->assertIsObject(new $modelClass());
        $this->assertInstanceOf(Model::class, new $modelClass());
    }


    /**
     * @test
     */
    #[Test]
    public function it_will_store_data_in_table()
    {

        $modelClass = config('request-response-logger.model');

        $record = $modelClass::create([]);

        $this->assertDatabaseHas(config('request-response-logger.table'), [
            'id' => $record->id,
        ]);
    }


    /**
     * @test
     */
    #[Test]
    public function it_can_filter_records_by_defined_scopes()
    {

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
    #[Test]
    public function it_can_handle_related_to_auth_user()
    {

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
    #[Test]
    public function it_can_cast_columns_on_store_and_retrival_as_defined_in_config()
    {

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


    /**
     * @test
     */
    #[Test]
    public function the_model_has_a_factory()
    {

        $modelClass = config('request-response-logger.model');

        $logs = $modelClass::factory()->count(3)->create();

        $this->assertCount(3, $logs);
        $this->assertEquals(3, $modelClass::count());
        $this->assertNotNull($logs->first()->uuid);
        $this->assertIsArray($logs->first()->request_headers);

        $this->assertTrue($modelClass::factory()->marked()->create()->marked);
        $this->assertEquals(1, $modelClass::marked()->count());
    }


    /**
     * @test
     * @runInSeparateProcess
     */
    #[Test]
    #[RunInSeparateProcess]
    public function the_model_uses_the_factory_of_the_app_when_defined()
    {

        require_once __DIR__ . '/App/database/factories/AppRequestResponseLoggerFactory.php';

        $modelClass = config('request-response-logger.model');

        $this->assertSame('APP', $modelClass::factory()->make()->request_method);
    }

}
