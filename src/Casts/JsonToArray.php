<?php

namespace Touhidurabir\RequestResponseLogger\Casts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class JsonToArray implements CastsAttributes {
    
    /**
     * Cast the given value.
     *
     * @param  object<\Illuminate\Database\Eloquent\Model>  $model
     * @param  string                                       $key
     * @param  mixed                                        $value
     * @param  array                                        $attributes
     * 
     * @return mixed
     */
    public function get($model, $key, $value = null, $attributes = []) {

        if ( config('request-response-logger.json_to_array_on_retrieve') && $this->isValidJson($value) ) {

            $value = json_decode($value, true);
        }

        return $value;
    }


    /**
     * Prepare the given value for storage.
     *
     * @param  object<\Illuminate\Database\Eloquent\Model>  $model
     * @param  string                                       $key
     * @param  mixed                                        $value
     * @param  array                                        $attributes
     * 
     * @return mixed
     */
    public function set($model, $key, $value = null, $attributes = []) {

        if ( is_array($value) ) {

            $value = json_encode($value);
        }

        return $value;
    }


    /**
     * Checks if the given expected value is valid json string.
     *
     * @param  string  $value
     * @return boolean
     */
    public function isValidJson(string $value) : bool {

        json_decode($value);
        
        return json_last_error() === JSON_ERROR_NONE;
    }

}
