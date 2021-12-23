<?php

namespace Touhidurabir\RequestResponseLogger\Jobs\Middleware;

use Throwable;
use Illuminate\Cache\RedisLock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class WithoutOverlappingOfCleaningJob {

    /**
     * Handle the acquire and release of lock
     *
     * @param  mixed        $job
     * @param  callable     $next
     * 
     * @return mixed
     */
    public function handle($job, $next) {

        try {

            $redis = Redis::connection();

            if ( $redis->ping() ) {

                $this->lockHandler($job, $next);
            }

        } catch (Throwable $exception) {

            $next($job);
        }
    }


    /**
     * Handle the redis locking process
     *
     * @param  mixed        $job
     * @param  callable     $next
     * 
     * @return mixed
     */
    protected function lockHandler($job, $next) {

        $lock = Cache::store(config('cache.default') ?? 'redis')
                    ->lock("{$job->resolveName()}_lock", 10 * 60);

        if ( ! $lock->get() ) {
            
            $job->delete();

            return;
        }

        $next($job);

        $lock->release();
    }
    
}