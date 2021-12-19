<?php

namespace Touhidurabir\RequestResponseLogger\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Touhidurabir\RequestResponseLogger\RequestResponseLogManager;
use Touhidurabir\RequestResponseLogger\Jobs\Middleware\WithoutOverlappingOfCleaningJob;

class DeleteRequestResponse implements ShouldQueue {

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
     * Create a new job instance.
     * 
     * @param  int  $keepTillLast
     * @param  bool $onlyUnmarked
     * 
     * @return void 
     */
    public function __construct(int $keepTillLast = null, bool $onlyUnmarked = false) {

        $this->keepTillLast = $keepTillLast;
        $this->onlyUnmarked = $onlyUnmarked;
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void {

        $numberOfRecordsToDeleted = RequestResponseLogManager::withQuery()
                                        ->keepTill($this->keepTillLast)
                                        ->withMarked(!$this->onlyUnmarked)
                                        ->remove(1000);
                                    
        
        if ($numberOfRecordsToDeleted > 0) {

            self::dispatch($this->keepTillLast, $this->onlyUnmarked);
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