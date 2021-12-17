<?php

namespace Touhidurabir\RequestResponseLogger\Console;

use Exception;
use Throwable;
use Illuminate\Console\Command;
use Touhidurabir\RequestResponseLogger\Console\Concerns\CommandExceptionHandler;

class RequestResponseLogCleaner extends Command {
    
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
                            {--soft-delete      : Run the clear up process as the soft delete}
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

            
        } catch (Throwable $exception) {
            
            $this->outputConsoleException($exception);

            return 1;
        }
    }

}