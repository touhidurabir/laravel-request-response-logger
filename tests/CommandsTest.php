<?php

namespace Touhidurabir\RequestResponseLogger\Tests;

use PHPUnit\Framework\Attributes\Test;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Tester\CommandTester;
use Touhidurabir\RequestResponseLogger\Concerns\FileHelpers;
use Touhidurabir\RequestResponseLogger\Jobs\DeleteRequestResponse;
use Touhidurabir\RequestResponseLogger\Console\RequestResponseLogCleaner;
use Touhidurabir\RequestResponseLogger\Console\RequestResponseLoggerExporter;
use Touhidurabir\RequestResponseLogger\Tests\Traits\LaravelTestBootstrapping;
use Touhidurabir\RequestResponseLogger\Tests\Traits\TestDatabaseBootstraping;

class CommandsTest extends TestCase
{
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
    protected function setUp(): void
    {

        parent::setUp();

        $this->clearCommand = $this->configureTestCommand(RequestResponseLogCleaner::class);
        $this->exportCommand = $this->configureTestCommand(RequestResponseLoggerExporter::class);

        $this->beforeApplicationDestroyed(
            fn () => File::delete(storage_path() . '/' . $this->exportFileName . '.csv')
        );
    }


    /**
     * Seed the logger table with dummy data
     *
     * @return void
     */
    protected function seedLoggerTable()
    {

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
    protected function configureTestCommand(string $commandClass)
    {

        $command = new $commandClass();
        $command->setLaravel($this->app);

        return new CommandTester($command);
    }


    /**
     * @test
     */
    #[Test]
    public function the_clear_command_will_run()
    {

        $this->assertEquals($this->clearCommand->execute([]), 0);

        $this->assertEquals($this->clearCommand->execute([
            '--keep-till-last'  => 24,
            '--only-unmarked'   => false,
        ]), 0);
    }


    /**
     * @test
     */
    #[Test]
    public function the_clear_command_will_delete_records_from__table()
    {

        $this->seedLoggerTable();

        $modelClass = config('request-response-logger.model');

        $this->assertEquals($modelClass::count(), 4);

        $this->assertEquals($this->clearCommand->execute([]), 0);

        $this->assertEquals($modelClass::count(), 0);
    }


    /**
     * @test
     */
    #[Test]
    public function the_export_command_will_run()
    {

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
    #[Test]
    public function the_export_command_will_generate_proper_file()
    {

        $this->assertEquals($this->exportCommand->execute([
            '--filename' => $this->exportFileName,
        ]), 0);

        $this->assertTrue(File::exists(storage_path() . '/' . $this->exportFileName . '.csv'));
    }


    /**
     * @test
     */
    #[Test]
    public function the_export_command_will_fail_if_eisxted_file_replacement_no_instructed()
    {

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
    #[Test]
    public function the_export_command_will_export_data_into_file()
    {

        $this->seedLoggerTable();

        $modelClass = config('request-response-logger.model');

        $this->assertEquals($this->exportCommand->execute([
            '--filename' => $this->exportFileName,
        ]), 0);

        $fp = file(storage_path() . '/' . $this->exportFileName . '.csv', FILE_SKIP_EMPTY_LINES);

        $this->assertEquals(count($fp), 5);
    }


    /**
     * @test
     */
    #[Test]
    public function the_export_command_will_export_only_the_records_of_last_given_hours()
    {

        DB::table(config('request-response-logger.table'))
            ->insert([
                ['response_status_code' => '200', 'created_at' => now()->subHours(48)],
                ['response_status_code' => '201', 'created_at' => now()->subHour()],
            ]);

        $this->assertEquals($this->exportCommand->execute([
            '--filename' => $this->exportFileName,
            '--of-last'  => 24,
        ]), 0);

        $lines = file(storage_path() . '/' . $this->exportFileName . '.csv', FILE_SKIP_EMPTY_LINES);

        $this->assertCount(2, $lines);
        $this->assertStringContainsString('201', $lines[1]);
    }


    /**
     * @test
     */
    #[Test]
    public function the_export_command_default_file_name_is_valid_on_every_os()
    {

        Carbon::setTestNow('2026-01-02 03:04:05');

        $file = storage_path() . '/2026-01-02_030405.csv';

        $this->beforeApplicationDestroyed(fn () => File::delete($file));

        $this->assertEquals(0, $this->exportCommand->execute([]));

        Carbon::setTestNow();

        $this->assertTrue(File::exists($file));
    }


    /**
     * @test
     */
    #[Test]
    public function the_export_command_will_restore_the_json_cast_setting()
    {

        $this->seedLoggerTable();

        $this->exportCommand->execute(['--filename' => $this->exportFileName]);

        $this->assertTrue(config('request-response-logger.json_to_array_on_retrieve'));

        // and when the export fails
        $this->exportCommand->execute(['--filename' => $this->exportFileName]);

        $this->assertTrue(config('request-response-logger.json_to_array_on_retrieve'));
    }


    /**
     * Read the rows of the export file
     *
     * @param  string|null $file
     * @return array
     */
    protected function exportedRows(?string $file = null): array
    {

        $handle = fopen($file ?? storage_path() . '/' . $this->exportFileName . '.csv', 'r');

        $rows = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }


    /**
     * @test
     */
    #[Test]
    public function the_clear_command_will_keep_the_records_of_last_given_hours()
    {

        DB::table(config('request-response-logger.table'))
            ->insert([
                ['response_status_code' => '200', 'created_at' => now()->subHours(48)],
                ['response_status_code' => '201', 'created_at' => now()->subHour()],
            ]);

        $this->assertEquals(0, $this->clearCommand->execute(['--keep-till-last' => 24]));

        $modelClass = config('request-response-logger.model');

        $this->assertEquals(1, $modelClass::count());
        $this->assertEquals('201', $modelClass::first()->response_status_code);
    }


    /**
     * @test
     */
    #[Test]
    public function the_clear_command_will_delete_only_the_unmarked_records()
    {

        $this->seedLoggerTable();

        $this->assertEquals(0, $this->clearCommand->execute(['--only-unmarked' => true]));

        $modelClass = config('request-response-logger.model');

        $this->assertEquals(2, $modelClass::count());
        $this->assertEquals(2, $modelClass::marked()->count());
    }


    /**
     * @test
     */
    #[Test]
    public function the_clear_command_will_delete_the_soft_deleted_records_as_well()
    {

        $this->seedLoggerTable();

        $modelClass = config('request-response-logger.model');

        $modelClass::first()->delete();

        $this->assertEquals(0, $this->clearCommand->execute([]));

        $this->assertEquals(0, $modelClass::withTrashed()->count());
    }


    /**
     * @test
     */
    #[Test]
    public function the_clear_command_will_delete_all_the_records_in_given_segments()
    {

        $this->seedLoggerTable();

        $this->assertEquals(0, $this->clearCommand->execute(['--limit' => 1]));

        $this->assertEquals(0, config('request-response-logger.model')::count());
    }


    /**
     * @test
     */
    #[Test]
    public function the_clear_command_will_dispatch_a_queue_job_with_on_job()
    {

        Queue::fake();

        $this->assertEquals(0, $this->clearCommand->execute([
            '--on-job'          => true,
            '--keep-till-last'  => 5,
            '--only-unmarked'   => true,
            '--limit'           => 3,
        ]));

        Queue::assertPushed(DeleteRequestResponse::class, function ($job) {
            return $job->keepTillLast === 5
                && $job->onlyUnmarked === false
                && $job->limit === 3
                && $job->method === 'dispatch';
        });
    }


    /**
     * @test
     */
    #[Test]
    public function the_clear_command_will_fail_when_the_cleaning_fails()
    {

        config(['request-response-logger.jobs.clear' => 'Not\\Existing\\CleaningJob']);

        $this->assertEquals(1, $this->clearCommand->execute([]));
        $this->assertStringContainsString('Exception Arise During Task Execution', $this->clearCommand->getDisplay());
    }


    /**
     * @test
     */
    #[Test]
    public function the_export_command_will_export_only_the_marked_records()
    {

        $this->seedLoggerTable();

        $this->assertEquals(0, $this->exportCommand->execute([
            '--filename'    => $this->exportFileName,
            '--only-marked' => true,
        ]));

        $rows = $this->exportedRows();

        $this->assertCount(3, $rows);
        $this->assertEqualsCanonicalizing(['401', '404'], array_column(array_slice($rows, 1), array_search('response_status_code', $rows[0])));
    }


    /**
     * @test
     */
    #[Test]
    public function the_export_command_will_export_the_soft_deleted_records_only_with_trashed()
    {

        $this->seedLoggerTable();

        config('request-response-logger.model')::first()->delete();

        $this->exportCommand->execute(['--filename' => $this->exportFileName]);

        $this->assertCount(4, $this->exportedRows());

        $this->exportCommand->execute(['--filename' => $this->exportFileName, '--replace' => true, '--with-trashed' => true]);

        $this->assertCount(5, $this->exportedRows());
    }


    /**
     * @test
     */
    #[Test]
    public function the_export_command_will_store_the_file_in_given_path()
    {

        $path = sys_get_temp_dir() . '/request-response-logger-export-' . uniqid();

        File::ensureDirectoryExists($path);

        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($path));

        $this->assertEquals(0, $this->exportCommand->execute([
            '--filename'    => $this->exportFileName,
            '--path'        => $path,
        ]));

        $this->assertTrue(File::exists($path . '/' . $this->exportFileName . '.csv'));
    }


    /**
     * @test
     */
    #[Test]
    public function the_export_command_will_fail_for_a_path_that_does_not_exist()
    {

        $this->assertEquals(1, $this->exportCommand->execute([
            '--filename'    => $this->exportFileName,
            '--path'        => '/not/existing/request-response-logger/path',
        ]));

        $this->assertStringContainsString('does not exists', $this->exportCommand->getDisplay());
    }


    /**
     * @test
     */
    #[Test]
    public function the_export_command_will_write_the_columns_and_the_raw_json()
    {

        $modelClass = config('request-response-logger.model');

        $log = $modelClass::create([
            'request_method'    => 'POST',
            'request_headers'   => ['accept' => 'application/json'],
            'request_body'      => ['name' => 'test user'],
        ]);

        $this->exportCommand->execute(['--filename' => $this->exportFileName]);

        $rows = $this->exportedRows();
        $columns = Schema::getColumnListing(config('request-response-logger.table'));

        $this->assertSame($columns, $rows[0]);

        $row = array_combine($columns, $rows[1]);

        $this->assertSame($log->uuid, $row['uuid']);
        $this->assertSame('POST', $row['request_method']);
        $this->assertSame('{"accept":"application\\/json"}', $row['request_headers']);
        $this->assertSame('{"name":"test user"}', $row['request_body']);
    }

}
