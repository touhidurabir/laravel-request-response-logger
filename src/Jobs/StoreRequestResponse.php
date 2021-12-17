<?php

namespace Touhidurabir\RequestResponseLogger\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class StoreRequestResponse implements ShouldQueue {

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
     * Storeable data
     * 
     * @var array
     */
    public $data;


    /**
     * Storeable data as batch
     * 
     * @var boolean
     */
    public $batch = false;


    /**
     * Create a new job instance.
     * 
     * @param  array $data
     * @param  bool  $batch
     * 
     * @return void 
     */
    public function __construct($data, $batch = false) {

        $this->data = $data;
        $this->batch = $batch;
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void {

        if ( $batch ) {

            $table = config('log-requests-and-responses.logging_model');

            DB::table($table)->insert($this->data);

            return;
        }

        $model = config('log-requests-and-responses.logging_model');

        (new $model)->create($this->data);
    }
}