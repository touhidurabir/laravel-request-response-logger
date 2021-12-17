<?php

namespace Touhidurabir\RequestResponseLogger\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
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
     * 
     * 
     * @var int
     */
    public $keepTillLast;


    public $onlyUnmarked;

    public $withTrashed;


    /**
     * Create a new job instance.
     * 
     * @param  array $data
     * @return void 
     */
    public function __construct(int $keepTillLast = null, bool $onlyUnmarked = false, bool $withTrashed = false) {

        $this->keepTillLast = $keepTillLast;
        $this->onlyUnmarked = $onlyUnmarked;
        $this->withTrashed  = $withTrashed;
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void {

        $modelClass = config('request-response-logger.model');

        $query = $modelClass::query();

        if ( $this->keepTillLast ) {

            $query = $query->where('created_at', '<', now()->subHours($this->keepTillLast));
        }

        if ( $this->onlyUnmarked ) {

            $query = $query->where('marked', false);
        }

        if ( $this->withTrashed ) {

            $query = $query->withTrasned();
        }
        $numberOfRecordsDeleted = $query->limit(1000)->delete();
        
        if ($numberOfRecordsDeleted > 0) {

            self::dispatch($this->keepTillLast, $this->onlyUnmarked, $this->withTrashed);
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