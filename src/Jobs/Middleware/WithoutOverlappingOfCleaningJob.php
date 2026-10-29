<?php

namespace Touhidurabir\RequestResponseLogger\Jobs\Middleware;

use Throwable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class WithoutOverlappingOfCleaningJob
{
    /**
     * The seconds after which a queued job that could not get the lock is tried again
     *
     * @var int
     */
    public $releaseAfter = 10;


    /**
     * Handle the acquire and release of lock
     *
     * @param  mixed        $job
     * @param  callable     $next
     *
     * @return mixed
     */
    public function handle($job, $next)
    {

        try {

            $redis = Redis::connection();

            $canLock = (bool) $redis->ping();

        } catch (Throwable $exception) {

            $canLock = false;
        }

        if (! $canLock) {

            return $next($job);
        }

        return $this->lockHandler($job, $next);
    }


    /**
     * Handle the redis locking process
     *
     * @param  mixed        $job
     * @param  callable     $next
     *
     * @return mixed
     */
    protected function lockHandler($job, $next)
    {

        try {

            $lock = Cache::store(config('cache.default') ?? 'redis')
                        ->lock(get_class($job) . '_lock', 10 * 60);

            $acquired = $lock->get();

        } catch (Throwable $exception) {

            // the cache store does not support locks, run the job without one
            return $next($job);
        }

        // another cleaning job is running; a queued job is tried again later,
        // a sync one is skipped
        if (! $acquired) {

            $job->release($this->releaseAfter);

            return;
        }

        try {

            return $next($job);

        } finally {

            $lock->release();
        }
    }

}
