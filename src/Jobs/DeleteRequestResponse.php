<?php

namespace Touhidurabir\RequestResponseLogger\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Touhidurabir\RequestResponseLogger\RequestResponseLogManager;
use Touhidurabir\RequestResponseLogger\Concerns\JobDispatchableMethod;
use Touhidurabir\RequestResponseLogger\Jobs\Middleware\WithoutOverlappingOfCleaningJob;

class DeleteRequestResponse implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    use JobDispatchableMethod;

    /**
     * The max tirex
     *
     * @var int
     */
    public $tries = 3;


    /**
     * Queue timeout
     *
     * @var int
     */
    public $timeout = 20;


    /**
     * Keep the last provided hours record
     *
     * @var int
     */
    public $keepTillLast;


    /**
     * Remove only unmarked data/recoreds
     *
     * @var bool
     */
    public $onlyUnmarked;


    /**
     * The number to records to delete a each call
     *
     * @var int
     */
    public $limit;


    /**
     * The continious recursive job dispatch method
     *
     * @var string
     */
    public $method;


    /**
     * Create a new job instance.
     *
     * @param  int      $keepTillLast
     * @param  bool     $onlyUnmarked
     * @param  int      $limit
     * @param  string   $dispatchMethod
     *
     * @return void
     */
    public function __construct(
        ?int    $keepTillLast   = null,
        ?bool   $onlyUnmarked   = null,
        ?int    $limit          = null,
        ?string $dispatchMethod = null
    ) {

        $this->keepTillLast = $keepTillLast;
        $this->onlyUnmarked = $onlyUnmarked;
        $this->limit        = $limit;
        $this->method       = $dispatchMethod;
    }


    /**
     * Execute the job.
     *
     * Running synchronously, all the segments are deleted here one after the other,
     * so that the whole cleaning holds the lock once. On a queue, one segment is
     * deleted per job and the next segment is queued, so each job stays short.
     *
     * @return void
     */
    public function handle(): void
    {

        do {

            $numberOfRecordsToDeleted = RequestResponseLogManager::withQuery()
                                            ->keepTill($this->keepTillLast)
                                            ->withMarkedStatus($this->onlyUnmarked)
                                            ->remove($this->limit);

        } while ($numberOfRecordsToDeleted > 0 && $this->limit && $this->runsSynchronously());


        if ($numberOfRecordsToDeleted > 0 && $this->limit && ! $this->runsSynchronously()) {

            $this->method = $this->getDispatchMethod(true);

            self::{$this->method}($this->keepTillLast, $this->onlyUnmarked, $this->limit, $this->method);
        }
    }


    /**
     * Determine if the job runs synchronously rather than on a queue
     *
     * @return bool
     */
    protected function runsSynchronously(): bool
    {

        return ! $this->job || $this->job instanceof SyncJob;
    }


    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {

        return [new WithoutOverlappingOfCleaningJob()];
    }

}
