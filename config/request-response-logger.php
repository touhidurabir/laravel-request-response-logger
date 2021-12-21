<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Table
    |--------------------------------------------------------------------------
    |
    | Define the database table name on migration.
    |
    */
    'table' => 'request_response_loggers',


    /*
    |--------------------------------------------------------------------------
    | Eloquent Model
    |--------------------------------------------------------------------------
    |
    | Define the model to for this database table.
    |
    */
    'model' => \Touhidurabir\RequestResponseLogger\Models\RequestResponseLogger::class,

    
    /*
    |--------------------------------------------------------------------------
    | Log/Store On Jobs
    |--------------------------------------------------------------------------
    |
    | Define should run storing process on Queue Job.
    |
    */
    'log_on_queue' => true,


    /*
    |--------------------------------------------------------------------------
    | Process Jobs
    |--------------------------------------------------------------------------
    |
    | The Queue jobs to handle different type of process like loggin in Database
    | table to clear/delete from the database table;
    |
    */
    'jobs' => [
        'log' => \Touhidurabir\RequestResponseLogger\Jobs\StoreRequestResponse::class,
        'clear' => \Touhidurabir\RequestResponseLogger\Jobs\DeleteRequestResponse::class,
    ],


    /*
    |--------------------------------------------------------------------------
    | JSON to ARRAY Cast
    |--------------------------------------------------------------------------
    |
    | Cast JSON data to array and and other way around.
    |
    */
    'json_to_array_on_retrieve' => true,


    /*
    |--------------------------------------------------------------------------
    | Redis List to Store
    |--------------------------------------------------------------------------
    |
    | Should use the redis list to store log data temp way to avoid continuous
    | database write.
    |
    */
    'store_on_redis' => true,


    /*
    |--------------------------------------------------------------------------
    | Redis Max List Length
    |--------------------------------------------------------------------------
    |
    | This define the max redis list length on which exceeds, it will initiate 
    | the process to move those data in batch to database table.
    |
    */
    'max_redis_count' => 1000,


    /*
    |--------------------------------------------------------------------------
    | Unique Redis Key name 
    |--------------------------------------------------------------------------
    |
    | The unique list key name for which the data will to stored in the redis.
    |
    */
    'redis_key_name' => 'request_response_log',


    /*
    |--------------------------------------------------------------------------
    | Redis Configuration
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */
    'redis_configs' => [
        'url'       => env('REDIS_URL'),
        'host'      => env('REDIS_HOST', '127.0.0.1'),
        'password'  => env('REDIS_PASSWORD', null),
        'port'      => env('REDIS_PORT', '6379'),
        'database'  => env('REDIS_DB', '0'),
    ],


    /*
    |--------------------------------------------------------------------------
    | Redis Failure Fallback
    |--------------------------------------------------------------------------
    |
    | If choose to store vai redis and for some reason failed , it will
    | try to run the log store process normally and in database table if
    | set to true. 
    |
    */
    'fallback_on_redis_failure' => true,


    /*
    |--------------------------------------------------------------------------
    | Number of records to delete in each job dispatch
    |--------------------------------------------------------------------------
    |
    | The number of records to delete in segmented order in case of async delete
    | through queue job.
    |
    */
    'delete_in_segment_count' => 1000,
];
