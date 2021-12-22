<?php

namespace Touhidurabir\RequestResponseLogger\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Touhidurabir\RequestResponseLogger\RequestResponseLogManager;
use Touhidurabir\RequestResponseLogger\Concerns\JobDispatchableMethod;
use Touhidurabir\RequestResponseLogger\Jobs\Middleware\WithoutOverlappingOfCleaningJob;

class DeleteRequestResponse implements ShouldQueue {

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
    public function __construct(int     $keepTillLast   = null, 
                                bool    $onlyUnmarked   = null, 
                                int     $limit          = null,
                                string  $dispatchMethod = null) {

        $this->keepTillLast = $keepTillLast;
        $this->onlyUnmarked = $onlyUnmarked;
        $this->limit        = $limit;
        $this->method       = $dispatchMethod;
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void {

        $numberOfRecordsToDeleted = RequestResponseLogManager::withQuery()
                                        ->keepTill($this->keepTillLast)
                                        ->withMarkedStatus($this->onlyUnmarked)
                                        ->remove($this->limit);
                                    
        
        if ($numberOfRecordsToDeleted > 0) {

            $this->method = $this->method ?? $this->getDispatchMethod(false);

            self::{$this->method}($this->keepTillLast, $this->onlyUnmarked, $this->limit, $this->method);
        }
    }


    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware() {

        return [new WithoutOverlappingOfCleaningJob];
    }

}