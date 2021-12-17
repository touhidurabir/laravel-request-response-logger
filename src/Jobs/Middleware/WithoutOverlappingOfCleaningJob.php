<?php

namespace Touhidurabir\RequestResponseLogger\Jobs\Middleware;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class WithoutOverlappingOfCleaningJob {

    public function handle($job, $next) {

        /** @var \Illuminate\Cache\RedisLock $lock */
        $lock = Cache::store('redis')->lock("{$job->resolveName()}_lock", 10 * 60);

        if (! $lock->get()) {
            
            $job->delete();

            return;
        }

        $next($job);

        $lock->release();
    }
}