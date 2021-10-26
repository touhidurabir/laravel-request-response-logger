<?php

namespace Touhidurabir\RequestResponseLogger\Facades;

use Illuminate\Support\Facades\Facade;

class RequestResponseLogger extends Facade {
    
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() {

        return 'request-response-logger';
    }
}