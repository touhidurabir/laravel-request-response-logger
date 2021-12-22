<?php

namespace Touhidurabir\RequestResponseLogger\Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Tester\CommandTester;
use Touhidurabir\RequestResponseLogger\Concerns\FileHelpers;
use Touhidurabir\RequestResponseLogger\Console\RequestResponseLogCleaner;
use Touhidurabir\RequestResponseLogger\Console\RequestResponseLoggerExporter;
use Touhidurabir\RequestResponseLogger\Tests\Traits\LaravelTestBootstrapping;
use Touhidurabir\RequestResponseLogger\Tests\Traits\TestDatabaseBootstraping;

class CommandsTest extends TestCase {

    use LaravelTestBootstrapping;

    use TestDatabaseBootstraping;

    // use FileHelpers;

    /**
     * The testable dummy command
     *
     * @var object<\Symfony\Component\Console\Tester\CommandTester>
     */
    protected $clearCommand;


    /**
     * The testable dummy command
     *
     * @var object<\Symfony\Component\Console\Tester\CommandTester>
     */
    protected $exportCommand;


    /**
     * The testable dummy export file name
     *
     * @var string
     */
    protected $exportFileName = 'request-response-logger-export';


    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void {

        parent::setUp();

        $this->clearCommand = $this->configureTestCommand(RequestResponseLogCleaner::class);
        $this->exportCommand = $this->configureTestCommand(RequestResponseLoggerExporter::class);

        $this->beforeApplicationDestroyed(
            fn() => File::delete(storage_path() . '/' . $this->exportFileName . '.csv')
        );
    }


    /**
     * Seed the logger table with dummy data
     *
     * @return void
     */
    protected function seedLoggerTable() {

        DB::table(config('request-response-logger.table'))
            ->insert([
                ['response_status_code' => '200', 'marked' => false],
                ['response_status_code' => '201', 'marked' => false],
                ['response_status_code' => '401', 'marked' => true],
                ['response_status_code' => '404', 'marked' => true],
            ]);
    }


    /**
     * Configure the testable command as dummy command
     * 
     * @param  string $commandClass
     * @return object<\Symfony\Component\Console\Tester\CommandTester>
     */
    protected function configureTestCommand(string $commandClass) {

        $command = new $commandClass;
        $command->setLaravel($this->app);

        return new CommandTester($command);
    }


    /**
     * @test
     */
    public function the_clear_command_will_run() {

        $this->assertEquals($this->clearCommand->execute([]), 0);

        $this->assertEquals($this->clearCommand->execute([
            '--keep-till-last'  => 24,
            '--only-unmarked'   => false,
        ]), 0);
    }


    /**
     * @test
     */
    public function the_clear_command_will_delete_records_from__table() {

        $this->seedLoggerTable();

        $modelClass = config('request-response-logger.model');

        $this->assertEquals($modelClass::count(), 4);

        $this->assertEquals($this->clearCommand->execute([]), 0);

        $this->assertEquals($modelClass::count(), 0);
    }


    /**
     * @test
     */
    public function the_export_command_will_run() {

        $this->assertEquals($this->exportCommand->execute([
            '--filename' => $this->exportFileName,
        ]), 0);

        $this->assertEquals($this->exportCommand->execute([
            '--filename' => $this->exportFileName,
            '--replace'  => true,
        ]), 0);
    }


    /**
     * @test
     */
    public function the_export_command_will_generate_proper_file() {

        $this->assertEquals($this->exportCommand->execute([
            '--filename' => $this->exportFileName,
        ]), 0);

        $this->assertTrue(File::exists(storage_path() . '/' . $this->exportFileName . '.csv'));
    }


    /**
     * @test
     */
    public function the_export_command_will_fail_if_eisxted_file_replacement_no_instructed() {

        $this->exportCommand->execute([
            '--filename' => $this->exportFileName,
        ]);

        $this->assertNotEquals($this->exportCommand->execute([
            '--filename' => $this->exportFileName,
        ]), 0);
    }


    /**
     * @test
     */
    public function the_export_command_will_export_data_into_file() {

        $this->seedLoggerTable();

        $modelClass = config('request-response-logger.model');

        $this->assertEquals($this->exportCommand->execute([
            '--filename' => $this->exportFileName,
        ]), 0);

        $fp = file(storage_path() . '/' . $this->exportFileName . '.csv', FILE_SKIP_EMPTY_LINES);

        $this->assertEquals(count($fp), 5);
    }

}