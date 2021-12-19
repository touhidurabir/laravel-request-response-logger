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
     * Static constructor to create a new instacne with initialized query builder instance
     * 
     * @return static
     */
    public static function withQuery() : static {

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
     * Define if should keep the marked records
     * 
     * @param  bool $deleteWithMarked
     * @return static
     */
    public function withMarked(bool $onlyMarked = false) : self {

        if ( ! $onlyMarked ) {

            $this->query = $this->query->where('marked', 'false');
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
     * @param  int $limit
     * @return int
     */
    public function remove(int $limit = 1000) : int {

        return $this->query->limit($limit)->forceDelete();
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
    public function store(Request $request, Response $response) : void {

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
    public function getStoreable(Request $request, Response $response) : array {

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

            $redis = Redis::connection(config('request-response-logger.redis_configs'));

            $listKey = config('request-response-logger.redis_key_name');

            $storeable = array_merge([
                'uuid' => UuidGenerator::uuid4(),
            ], $storeable);

            $redis->rpush($listKey, json_encode($storeable));

            $maxRedisListLimit = abs(config('request-response-logger.max_redis_count') ?? 1000);

            if ( $redis->llen($listKey) >= $maxRedisListLimit ) {

                while( $storeables = $redis->lrange($listKey, 0, $maxRedisListLimit-1) ) {

                    $this->storeInDatabase(
                        collect($storeables)->map(fn($data) => json_decode($data, true))->toArray(), 
                        true
                    );
                    
                    $redis->ltrim($listKey, $maxRedisListLimit, -1);
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

}