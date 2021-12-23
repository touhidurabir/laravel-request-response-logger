<?php

namespace Touhidurabir\RequestResponseLogger\Console;

use Exception;
use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Touhidurabir\RequestResponseLogger\Concerns\JobDispatchableMethod;
use Touhidurabir\RequestResponseLogger\Console\Concerns\CommandExceptionHandler;

class RequestResponseLoggerRedisImport extends Command {

    use JobDispatchableMethod;

    /**
     * Process the handeled exception and provide output
     */
    use CommandExceptionHandler;


    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'request-response-logger:redis-import';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import stored data from redis to database table';



    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        
        parent::__construct();
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        
        $this->info('Initiating the importing from redis storage');

        try {

            $redis = Redis::connection(config('request-response-logger.redis_configs'));
            
            $listKey = config('request-response-logger.redis_key_name');

            $itemsInListCount = $redis->llen($listKey);

            if ( $itemsInListCount <= 0 ) {

                $this->info('Redis list is empty. No data to import');

                return self::SUCCESS;
            }

            $storeInSegmentCount = abs(config('request-response-logger.redis_store_in_segment_count') ?? 500);

            $method = $this->getDispatchMethod(config('request-response-logger.log_on_queue'));

            $jobClass = config('request-response-logger.jobs.log');

            $this->info('Importing ' . $itemsInListCount . ' records into the database table');

            while( $storeables = $redis->lrange($listKey, 0, $storeInSegmentCount-1) ) {

                $jobClass::$method(
                    collect($storeables)->map(fn($data) => json_decode($data, true))->toArray(), 
                    true
                );
                
                $redis->ltrim($listKey, $storeInSegmentCount, -1);
            }
 
            $this->info('Importing process has completed successfully');

            return self::SUCCESS;
            
        } catch (Throwable $exception) {

            ray($exception);
            
            $this->outputConsoleException($exception);

            return self::FAILURE;
        }
    }
}