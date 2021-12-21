<?php

namespace Touhidurabir\RequestResponseLogger\Tests;

use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Touhidurabir\RequestResponseLogger\Console\RequestResponseLogCleaner;
use Touhidurabir\RequestResponseLogger\Console\RequestResponseLoggerExporter;
use Touhidurabir\RequestResponseLogger\Tests\Traits\LaravelTestBootstrapping;
use Touhidurabir\RequestResponseLogger\Tests\Traits\TestDatabaseBootstraping;

class CommandsTest extends TestCase {

    use LaravelTestBootstrapping;

    use TestDatabaseBootstraping;

    protected $clearCommand;

    protected $exportCommand;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void {

        parent::setUp();

        $this->clearCommand = $this->configureTestCommand(RequestResponseLogCleaner::class);
        $this->exportCommand = $this->configureTestCommand(RequestResponseLoggerExporter::class);
    }


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

        $this->assertEquals($this->clearCommand->execute([]), 0);
    }
}