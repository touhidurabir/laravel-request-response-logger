<?php

namespace Touhidurabir\RequestResponseLogger\Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\File;
use Touhidurabir\RequestResponseLogger\Tests\Traits\LaravelTestBootstrapping;

class ConfigTest extends TestCase {
    
    use LaravelTestBootstrapping;

    /**
     * @test
     */
    public function it_will_have_access_to_configs() {

        $this->assertNotNull(config('request-response-logger'));
        $this->assertIsArray(config('request-response-logger'));
    }
    
}