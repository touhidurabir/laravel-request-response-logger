<?php

namespace Touhidurabir\RequestResponseLogger\Tests;

use PHPUnit\Framework\Attributes\Test;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Touhidurabir\RequestResponseLogger\Tests\App\Models\User;
use Touhidurabir\RequestResponseLogger\Jobs\StoreRequestResponse;
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

        Route::any('/request-response-logger-failing-test', function () {

            return response('The given data was invalid', 422);

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


    /**
     * @test
     */
    #[Test]
    public function middleware_will_not_save_the_hidden_headers()
    {

        $this->withHeaders([
            'Authorization' => 'Bearer secret-token',
            'X-Custom'      => 'custom-value',
        ])->withCookie('some_cookie', 'cookie-value')->get('/request-response-logger-test');

        $log = RequestResponseLogger::first();

        $this->assertArrayNotHasKey('authorization', $log->request_headers);
        $this->assertArrayNotHasKey('cookie', $log->request_headers);
        $this->assertSame('custom-value', $log->request_headers['x-custom']);

        $this->assertArrayNotHasKey('set-cookie', $log->response_headers);
        $this->assertArrayHasKey('content-type', $log->response_headers);
    }


    /**
     * @test
     */
    #[Test]
    public function middleware_will_save_the_headers_that_are_not_hidden()
    {

        config(['request-response-logger.hidden_headers' => ['X-Custom']]);

        $this->withHeaders([
            'Authorization' => 'Bearer secret-token',
            'X-Custom'      => 'custom-value',
        ])->get('/request-response-logger-test');

        $log = RequestResponseLogger::first();

        $this->assertSame('Bearer secret-token', $log->request_headers['authorization']);
        $this->assertArrayNotHasKey('x-custom', $log->request_headers);
        $this->assertArrayHasKey('set-cookie', $log->response_headers);
    }


    /**
     * @test
     */
    #[Test]
    public function middleware_saves_every_detail_of_the_request_and_response()
    {

        $this->get('/request-response-logger-test');

        $log = RequestResponseLogger::first();

        $this->assertNotNull($log->uuid);
        $this->assertNotNull($log->created_at);
        $this->assertSame('GET', $log->request_method);
        $this->assertSame('http://localhost/request-response-logger-test', $log->request_url);
        $this->assertSame('127.0.0.1', $log->request_ip);
        $this->assertNull($log->request_auth_user_id);
        $this->assertNull($log->request_body);
        $this->assertSame('localhost', $log->request_headers['host']);
        $this->assertSame('application/json', $log->response_headers['content-type']);
        $this->assertSame(['status' => true, 'data' => []], $log->response_body);
        $this->assertEquals(200, $log->response_status_code);
        $this->assertFalse((bool) $log->marked);
    }


    /**
     * @test
     */
    #[Test]
    public function middleware_does_not_change_the_response()
    {

        $this->postJson('/request-response-logger-test', ['name' => 'test user'])
            ->assertOk()
            ->assertExactJson(['status' => true, 'data' => ['name' => 'test user']]);
    }


    /**
     * @test
     */
    #[Test]
    public function middleware_saves_the_failed_responses()
    {

        $this->get('/request-response-logger-failing-test')->assertStatus(422);

        $log = RequestResponseLogger::first();

        $this->assertEquals(422, $log->response_status_code);
        $this->assertSame('The given data was invalid', $log->response_body);
        $this->assertEquals(1, RequestResponseLogger::failed()->count());
        $this->assertEquals(0, RequestResponseLogger::successful()->count());
    }


    /**
     * @test
     */
    #[Test]
    public function middleware_will_not_save_the_nested_hidden_request_fields()
    {

        config(['request-response-logger.hidden_request_fields' => ['user.password']]);

        $this->postJson('/request-response-logger-test', [
            'user' => ['name' => 'test user', 'password' => 'secret'],
        ]);

        $this->assertSame(['user' => ['name' => 'test user']], RequestResponseLogger::first()->request_body);
    }


    /**
     * @test
     */
    #[Test]
    public function middleware_queues_the_log_with_log_on_queue()
    {

        Queue::fake();

        config(['request-response-logger.log_on_queue' => true]);

        $this->get('/request-response-logger-test');

        $this->assertEquals(0, RequestResponseLogger::count());

        Queue::assertPushed(StoreRequestResponse::class, function ($job) {
            return $job->batch === false && $job->data['request_method'] === 'GET';
        });
    }

}
