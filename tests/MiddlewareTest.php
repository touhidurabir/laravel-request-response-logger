<?php

use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Touhidurabir\RequestResponseLogger\Tests\App\Models\User;
use Touhidurabir\RequestResponseLogger\Tests\App\Models\Profile;
use Touhidurabir\RequestResponseLogger\Models\RequestResponseLogger;
use Touhidurabir\RequestResponseLogger\Middlewares\LogRequestResponse;
use Touhidurabir\RequestResponseLogger\Tests\Traits\LaravelTestBootstrapping;

class MiddlewareTest extends TestCase { 

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
     * Define routes setup.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    protected function defineRoutes($router) {
        
        Route::any('/request-response-logger-test', function() {
            
            return response()->json([
                'status'    => true,
                'data'      => request()->all(),
            ]);

        })->middleware(['web', LogRequestResponse::class]);
    }


    /**
     * @test
     */
    public function middleware_saves_the_data_in_table() {

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
    public function middleware_will_save_data_along_with_auth_user() {

        $user = User::create(['email' => 'testuser@test.com', 'password' => Hash::make('123456')]);

        $this->be($user);

        $this->post('/request-response-logger-test');
        $log = RequestResponseLogger::first();

        $this->assertNotNull($log->request_auth_user_id);
        $this->assertEquals($log->request_auth_user_id, $user->id);
    }

    
}