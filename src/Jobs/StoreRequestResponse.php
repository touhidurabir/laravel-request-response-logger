<?php

namespace Touhidurabir\RequestResponseLogger\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class StoreRequestResponse implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
    public function __construct($data, $batch = false)
    {

        $this->data = $data;
        $this->batch = $batch;
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {

        if ($this->batch) {

            $table = config('request-response-logger.table');

            DB::table($table)->insert($this->withoutStoredLogs($table, $this->data));

            return;
        }

        $model = config('request-response-logger.model');

        (new $model())->create($this->data);
    }


    /**
     * Remove the logs that are already in the table
     *
     * A batch is only removed from redis after it is stored, so a failure in
     * between can bring the same logs again. The uuid column is unique, so
     * inserting them again would fail the whole batch, every time.
     *
     * @param  string $table
     * @param  array  $logs
     *
     * @return array
     */
    protected function withoutStoredLogs(string $table, array $logs): array
    {

        $uuids = array_filter(array_column($logs, 'uuid'));

        if (empty($uuids)) {

            return $logs;
        }

        $storedUuids = DB::table($table)->whereIn('uuid', $uuids)->pluck('uuid')->all();

        return array_values(array_filter($logs, function ($log) use ($storedUuids) {
            return ! in_array($log['uuid'] ?? null, $storedUuids, true);
        }));
    }
}
