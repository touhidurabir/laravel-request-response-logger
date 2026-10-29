<?php

namespace Touhidurabir\RequestResponseLogger\Tests;

use PHPUnit\Framework\Attributes\Test;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Touhidurabir\RequestResponseLogger\Tests\App\Models\User;
use Touhidurabir\RequestResponseLogger\Models\RequestResponseLogger;
use Touhidurabir\RequestResponseLogger\Middlewares\LogRequestResponse;
use Touhidurabir\RequestResponseLogger\Tests\Traits\LaravelTestBootstrapping;
use Touhidurabir\RequestResponseLogger\Tests\Traits\TestDatabaseBootstraping;

class MiddlewareTest extends TestCase
{
    use LaravelTestBootstrapping;

    use TestDatabaseBootstraping;

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
     * @test
     */
    #[Test]
    public function middleware_saves_the_data_in_table()
    {

        $request = $this->get('/request-response-logger-test');
        $log = RequestResponseLogger::first();

        $this->assertSame($log->request_method, 'GET');

        $request = $this->post('/request-response-logger-test');
        $log = RequestResponseLogger::orderBy('id', 'desc')->first();

        $this->assertSame($log->request_method, 'POST');
    }


    /**
     * @test
     */
    #[Test]
    public function middleware_will_save_data_along_with_auth_user()
    {

        $user = User::create(['email' => 'testuser@test.com', 'password' => Hash::make('123456')]);

        $this->be($user);

        $this->post('/request-response-logger-test');
        $log = RequestResponseLogger::first();

        $this->assertNotNull($log->request_auth_user_id);
        $this->assertEquals($log->request_auth_user_id, $user->id);
    }


    /**
     * @test
     */
    #[Test]
    public function middleware_saves_the_request_body()
    {

        $this->post('/request-response-logger-test', ['name' => 'test user']);
        $log = RequestResponseLogger::first();

        $this->assertSame(['name' => 'test user'], $log->request_body);

        $this->postJson('/request-response-logger-test', ['name' => 'json user']);
        $log = RequestResponseLogger::orderBy('id', 'desc')->first();

        $this->assertSame(['name' => 'json user'], $log->request_body);
    }


    /**
     * @test
     */
    #[Test]
    public function middleware_will_not_save_the_hidden_request_fields()
    {

        $this->post('/request-response-logger-test', [
            'email'                 => 'testuser@test.com',
            'password'              => 'secret',
            'password_confirmation' => 'secret',
        ]);
        $log = RequestResponseLogger::first();

        $this->assertSame(['email' => 'testuser@test.com'], $log->request_body);

        config(['request-response-logger.hidden_request_fields' => ['email']]);

        $this->post('/request-response-logger-test', [
            'email'     => 'testuser@test.com',
            'password'  => 'secret',
        ]);
        $log = RequestResponseLogger::orderBy('id', 'desc')->first();

        $this->assertSame(['password' => 'secret'], $log->request_body);
    }


    /**
     * @test
     */
    #[Test]
    public function middleware_saves_the_raw_request_content_when_it_is_not_arrayable()
    {

        $this->call('POST', '/request-response-logger-test', [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'plain text body');
        $log = RequestResponseLogger::first();

        $this->assertSame('plain text body', $log->request_body);
    }


}
