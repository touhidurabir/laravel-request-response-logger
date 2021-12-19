<?php

namespace Touhidurabir\RequestResponseLogger\Concerns;

trait JobDispatchableMethod {

    /**
     * provide Legacy support for older versions of Laravel that named the synchronous
     * `dispatchNow` as well as newer versions that use `dispatchSync`.
     *
     * @param  bool $setting
     * @return string
     */
    protected function getDispatchMethod(bool $setting) : string {

        if ( $setting ) {

            return 'dispatch';
        }
        
        return (int)app()->version() < 8 ? 'dispatchNow' : 'dispatchSync';
    }
}