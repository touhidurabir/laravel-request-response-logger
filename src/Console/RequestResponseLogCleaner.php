<?php

namespace Touhidurabir\RequestResponseLogger\Console;

use Exception;
use Throwable;
use Illuminate\Console\Command;
use Touhidurabir\RequestResponseLogger\Concerns\JobDispatchableMethod;
use Touhidurabir\RequestResponseLogger\Console\Concerns\CommandExceptionHandler;

class RequestResponseLogCleaner extends Command {

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
    protected $signature = 'request-response-logger:clear
                            {--keep-till-last=  : Keep the record that has stored in the last given hours}
                            {--only-unmarked    : Delete only the unmarked records}
                            {--on-job           : Run the deletion process through a Queue Job}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear out the request reponse log tables';



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
        
        $this->info('Cleaning the records');

        try {

            $queueJob = config('request-response-logger.jobs.clear');

            $method = $this->getDispatchMethod($this->option('on-job'));

            $queueJob::{$method}($this->option('keep-till-last'), $this->option('only-unmarked'));
            
        } catch (Throwable $exception) {
            
            $this->outputConsoleException($exception);

            return 1;
        }
    }

}