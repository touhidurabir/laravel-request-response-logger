<?php

namespace Touhidurabir\RequestResponseLogger;

use Exception;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Illuminate\Database\Eloquent\Builder;
use Touhidurabir\ModelUuid\UuidGenerator\Generator as UuidGenerator;
use Touhidurabir\RequestResponseLogger\Concerns\JobDispatchableMethod;

class RequestResponseLogManager {

    use JobDispatchableMethod;

    /**
     * The eloquent query builder instance
     * 
     * @var object<\Illuminate\Database\Eloquent\Builder>
     */
    protected $query;


    /**
     * The redis connector instance
     * 
     * @var object<\Illuminate\Redis\Connectors\PhpRedisConnector>
     */
    protected $redis;


    /**
     * Static constructor to create a new instance with passed pre configured redis connection
     * 
     * @param  object redis
     * @return self
     */
    public static function withRedis($redis) : self {

        $static = new static;

        $static->redis = $redis;

        return $static;
    }


    /**
     * Static constructor to create a new instacne with initialized query builder instance
     * 
     * @return static
     */
    public static function withQuery() : self {

        $static = new static;

        $modelClass = config('request-response-logger.model');

        $static->query = $modelClass::query();

        return $static;
    }


    /**
     * Set the keep till last defined hours records
     * 
     * @param  int $hours
     * @return static
     */
    public function keepTill(int $hours = null) : self {

        if ( $hours ) {

            $this->query = $this->query->where('created_at', '<', now()->subHours($hours));
        }

        return $this;
    }


    /**
     * Define if should pull records with specific marked status
     * 
     * @param  bool $marked
     * @return static
     */
    public function withMarkedStatus(bool $marked = null) : self {

        if ( ! is_null($marked) ) {

            $this->query = $this->query->where('marked', $marked);
        }

        return $this;
    }


    /**
     * Define if should take soft deleted record into account
     * 
     * @param  bool $onlyTrashed
     * @return static
     */
    public function withTrashed(bool $onlyTrashed = false) : self {

        if ( $onlyTrashed ) {

            $this->query = $this->query->withTrashed();
        }

        return $this;
    }


    /**
     * Force delete records from the table
     * 
     * @param  mixed<int|null> $limit
     * @return int
     */
    public function remove(int $limit = null) : int {

        if ( $limit ) {

            return $this->query->limit($limit)->forceDelete();
        }

        return $this->query->forceDelete();
    }


    /**
     * Get the current query builder 
     * 
     * @return object<\Illuminate\Database\Eloquent\Builder>
     */
    public function getQuery() {

        return $this->query;
    }


    /**
     * Store the request and response log
     * 
     * @param  object<\Illuminate\Http\Request>     $request
     * @param  object<\Illuminate\Http\Response>    $response
     * 
     * @return void
     */
    public function store($request, $response) : void {

        $storebale = $this->getStoreable($request, $response);

        if ( ! config('request-response-logger.store_on_redis') ) {

            $this->storeInDatabase($storebale);

            return;
        }

        $this->storeInRedis($storebale);
    }


    /**
     * Get the storeable data
     * 
     * @param  object<\Illuminate\Http\Request>     $request
     * @param  object<\Illuminate\Http\Response>    $response
     * 
     * @return array
     */
    public function getStoreable($request, $response) : array {

        return [
            'request_method'        => $request->method(),
            'request_headers'       => collect($request->headers->all())
                                            ->transform(function ($item) {
                                                return head($item);
                                            }) ?? [],
            'request_body'          => $this->getRequestBody($request),
            'request_url'           => $request->url(),
            'request_ip'            => $request->ip(),
            'request_auth_user_id'  => optional($request->user())->id,
            'response_headers'      => collect($response->headers->all())
                                            ->transform(function ($item) {
                                                return head($item);
                                            }) ?? [],
            'response_body'         => $response->getContent(),
            'response_status_code'  => $response->status(),
        ];
    }


    /**
     * Store the log data in the database table
     * 
     * @param  array    $storeable
     * @param  bool     $onBatch
     * 
     * @return void
     */
    protected function storeInDatabase(array $storeable, bool $onBatch = false) : void {

        $method = $this->getDispatchMethod(config('request-response-logger.log_on_queue'));

        $jobClass = config('request-response-logger.jobs.log');

        $jobClass::$method($storeable, $onBatch);
    }


    /**
     * Store the log data in the redis
     * 
     * @param  array    $storeable
     * @return void
     * 
     * @throws \Exception
     */
    protected function storeInRedis($storeable) {

        try {

            $redis = $this->getRedisInstance();

            $listKey = config('request-response-logger.redis_key_name');

            $storeable = array_merge($storeable, [
                'uuid'              => UuidGenerator::uuid4(),
                'request_headers'   => $this->stringify($storeable['request_headers']),
                'request_body'      => $this->stringify($storeable['request_body']),
                'response_headers'  => $this->stringify($storeable['response_headers']),
                'response_body'     => $this->stringify($storeable['response_body']),
            ]);

            $redis->rpush($listKey, json_encode($storeable));

            $maxRedisListLimit = abs(config('request-response-logger.max_redis_count') ?? 10000);

            $storeInSegmentCount = abs(config('request-response-logger.redis_store_in_segment_count') ?? 500);

            if ( $redis->llen($listKey) >= $maxRedisListLimit ) {

                while( $storeables = $redis->lrange($listKey, 0, $storeInSegmentCount-1) ) {

                    $this->storeInDatabase(
                        collect($storeables)->map(fn($data) => json_decode($data, true))->toArray(), 
                        true
                    );
                    
                    $redis->ltrim($listKey, $storeInSegmentCount, -1);
                }
            }

        } catch (Throwable $exception) {

            if ( config('request-response-logger.fallback_on_redis_failure') ) {

                $this->storeInDatabase($storeable);

                return;
            }

            throw $exception;
        }
    }


    /**
     * Check and determine if the request is JSON/Arrayable/XML or
     * some sort of plain text which is not arrayable
     * 
     * @param  object<\Illuminate\Http\Request> $request
     * @return mixed<array|string|null>
     */
    protected function getRequestBody(Request $request) {

        $requestBody = $request->all();

        if ( ! empty($requestBody) ) {

            $requestBody;
        }

        if ( empty($requestBody) && !empty($request->getContent()) ) {

            return $request->getContent();
        }

        return null;
    }


    /**
     * Get the useable redis instance
     * 
     * @return object<\Illuminate\Redis\Connectors\PhpRedisConnector>
     */
    protected function getRedisInstance() {

        return $this->redis ?? Redis::connection(config('request-response-logger.redis_configs'));
    }


    /**
     * Convert passed data to string
     * 
     * @return mixed<null|string>
     */
    protected function stringify($data) : ?string {
        
        if ( $data instanceof Collection ) {
            
            return json_encode($data->toArray());
        }

        if ( is_array($data) ) {

            return json_encode($data);
        }

        return $data;
    }

}