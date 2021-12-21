<?php

namespace Touhidurabir\RequestResponseLogger\Middlewares;

use Closure;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Touhidurabir\RequestResponseLogger\RequestResponseLogManager;

class LogRequestResponse {

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next) {

        return $next($request);
    }


    /**
     * Execute terminable actions after the response is returned.
     *
     * @param  object<\Illuminate\Http\Request>     $request
     * @param  object<\Illuminate\Http\Response>    $response
     * 
     * @return void
     */
    public function terminate($request, $response) : void {

        (new RequestResponseLogManager)->store($request, $response);
    }

}