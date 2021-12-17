<?php

namespace Touhidurabir\RequestResponseLogger\Console;

use Exception;
use Throwable;
use Illuminate\Console\Command;
use Touhidurabir\RequestResponseLogger\Console\Concerns\CommandExceptionHandler;

class RequestResponseLoggerExporter extends Command {
    
    /**
     * Process the handeled exception and provide output
     */
    use CommandExceptionHandler;


    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'request-response-logger:export
                            {name               : Name of the CSV file}
                            {--path=            : The absolute file store path, default to Storage folder}
                            {--only-marked      : Export only marked records}
                            {--with-trashed     : Expoer records along with soft deleted entries}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export records as CSV from the request response logger table';



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
        
        $this->info('Initiating the exporting');

        try {

            $this->info('Exporting has completed');
            
        } catch (Throwable $exception) {
            
            $this->outputConsoleException($exception);

            return 1;
        }
    }

}