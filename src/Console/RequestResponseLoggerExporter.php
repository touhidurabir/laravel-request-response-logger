<?php

namespace Touhidurabir\RequestResponseLogger\Console;

use Exception;
use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Touhidurabir\RequestResponseLogger\Concerns\FileHelpers;
use Touhidurabir\RequestResponseLogger\RequestResponseLogManager;
use Touhidurabir\RequestResponseLogger\Console\Concerns\CommandExceptionHandler;

class RequestResponseLoggerExporter extends Command {

    use FileHelpers;
    
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
                            {--filename=        : Name of the CSV file to store in storage directory}
                            {--path=            : The absolute file store path if decided to store other than storage directory}
                            {--of-last=         : Export only last provided hours records}
                            {--replace          : If such file exists at given location, replace it with new file}
                            {--only-marked      : Export only marked records}
                            {--with-trashed     : Export records along with soft deleted entries}';


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

            $file = $this->generateFileToExport();

            $fp = fopen($file, 'w');

            // make sure that we do not convery JSON to Array on retrival for CSV export
            Config::set('request-response-logger.json_to_array_on_retrieve', false);

            // Write the headers
            fputcsv($fp, DB::getSchemaBuilder()->getColumnListing(config('request-response-logger.table')));

            // load the records to export as lazy collection
            $records = RequestResponseLogManager::withQuery()
                            ->keepTill($this->option('of-last'))
                            ->withMarkedStatus($this->option('only-marked') ? true : null)
                            ->withTrashed($this->option('with-trashed'))
                            ->getQuery()
                            ->cursor();
            
            // write the data/records in the CSV file
            foreach ($records as $record) {
                
                fputcsv($fp, $record->toArray());
            }

            fclose($fp);

            $this->info('Exporting has completed');

            return self::SUCCESS;
            
        } catch (Throwable $exception) {

            // ray($exception);
            
            $this->outputConsoleException($exception);

            return self::FAILURE;
        }
    }


    /**
     * Generate the file to write content
     *
     * @return string
     * @throws \Exception
     */
    protected function generateFileToExport() : string {

        $name = ($this->option('filename') ?? str_replace(' ', '_', now()->toString())) . '.csv';

        $path = storage_path();

        if ( $this->option('path') ) {

            $path = $this->option('path');

            if ( ! $this->isDirectory($path) ) {

                throw new Exception(sprintf("The given path [%s] does not exists", $path));
            }
        }

        $fileFullPath = $path . '/' . $name;

        if ( $this->fileExists($fileFullPath) ) {

            if ( ! $this->option('replace') ) {

                throw new Exception(sprintf("The given file [%s] already existed at given [%s] path", $name, $path));
            }

            $this->removeFile($fileFullPath);

            $this->newFileWithContent($fileFullPath, '');
        }

        return $fileFullPath;
    }

}