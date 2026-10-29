<?php

namespace Touhidurabir\RequestResponseLogger\Tests;

use Mockery;
use RuntimeException;
use PHPUnit\Framework\Attributes\Test;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Touhidurabir\RequestResponseLogger\Tests\App\RedisFaker;
use Touhidurabir\RequestResponseLogger\Jobs\DeleteRequestResponse;
use Touhidurabir\RequestResponseLogger\Tests\Traits\LaravelTestBootstrapping;
use Touhidurabir\RequestResponseLogger\Tests\Traits\TestDatabaseBootstraping;
use Touhidurabir\RequestResponseLogger\Jobs\Middleware\WithoutOverlappingOfCleaningJob;

class CleaningJobTest extends TestCase
{
    use LaravelTestBootstrapping;

    use TestDatabaseBootstraping;

    /**
     * The cleaning job lock name
     *
     * @var string
     */
    protected $lockName = DeleteRequestResponse::class . '_lock';


    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {

        parent::setUp();

        // a reachable redis, so the cleaning job locks with the array cache store
        $this->app->singleton('redis', function ($app) {

            return new RedisFaker();
        });

        DB::table(config('request-response-logger.table'))
            ->insert([
                ['response_status_code' => '200', 'marked' => false],
                ['response_status_code' => '201', 'marked' => false],
                ['response_status_code' => '401', 'marked' => true],
                ['response_status_code' => '404', 'marked' => true],
            ]);
    }


    /**
     * @test
     */
    #[Test]
    public function the_sync_cleaning_job_deletes_all_the_segments_in_one_job()
    {

        $processedJobs = 0;

        Event::listen(JobProcessing::class, function () use (&$processedJobs) {
            $processedJobs++;
        });

        DeleteRequestResponse::dispatchSync(null, null, 2, 'dispatchSync');

        $this->assertEquals(0, config('request-response-logger.model')::count());
        $this->assertEquals(1, $processedJobs);
    }


    /**
     * @test
     */
    #[Test]
    public function the_cleaning_job_releases_the_lock_when_done()
    {

        DeleteRequestResponse::dispatchSync(null, null, 2, 'dispatchSync');

        $this->assertTrue(Cache::lock($this->lockName, 10)->get());
    }


    /**
     * @test
     */
    #[Test]
    public function the_cleaning_job_will_not_run_while_another_one_holds_the_lock()
    {

        Cache::lock($this->lockName, 600)->get();

        DeleteRequestResponse::dispatchSync(null, null, 2, 'dispatchSync');

        $this->assertEquals(4, config('request-response-logger.model')::count());
    }


    /**
     * @test
     */
    #[Test]
    public function the_queued_cleaning_job_is_released_back_while_another_one_holds_the_lock()
    {

        Cache::lock($this->lockName, 600)->get();

        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldReceive('release')->once()->with(10);

        $job = new DeleteRequestResponse(null, null, 2, 'dispatch');
        $job->setJob($queueJob);

        $ran = false;

        (new WithoutOverlappingOfCleaningJob())->handle($job, function () use (&$ran) {
            $ran = true;
        });

        $this->assertFalse($ran);
    }


    /**
     * @test
     */
    #[Test]
    public function the_failing_cleaning_job_runs_once_and_releases_the_lock()
    {

        $runs = 0;

        try {

            (new WithoutOverlappingOfCleaningJob())->handle(new DeleteRequestResponse(), function () use (&$runs) {

                $runs++;

                throw new RuntimeException('The cleaning failed');
            });

            $this->fail('The cleaning job exception was swallowed');

        } catch (RuntimeException $exception) {

            $this->assertSame('The cleaning failed', $exception->getMessage());
        }

        $this->assertEquals(1, $runs);
        $this->assertTrue(Cache::lock($this->lockName, 10)->get());
    }


    /**
     * @test
     */
    #[Test]
    public function the_queued_cleaning_job_deletes_one_segment_and_queues_the_next()
    {

        Queue::fake();

        $job = new DeleteRequestResponse(null, null, 2, 'dispatch');
        $job->setJob(Mockery::mock(QueueJob::class));

        $job->handle();

        $this->assertEquals(2, config('request-response-logger.model')::count());

        Queue::assertPushed(DeleteRequestResponse::class, 1);
    }


    /**
     * @test
     */
    #[Test]
    public function the_cleaning_job_runs_without_a_lock_when_redis_is_unreachable()
    {

        Cache::lock($this->lockName, 600)->get();

        $this->app->singleton('redis', function ($app) {

            return new class () {
                public function connection($name = null)
                {
                    throw new RuntimeException('Redis is unreachable');
                }
            };
        });

        Facade::clearResolvedInstance('redis');

        DeleteRequestResponse::dispatchSync(null, null, 2, 'dispatchSync');

        $this->assertEquals(0, config('request-response-logger.model')::count());
    }


    /**
     * @test
     */
    #[Test]
    public function the_cleaning_job_runs_without_a_lock_when_the_cache_store_cannot_lock()
    {

        config(['cache.default' => 'not-configured-store']);

        DeleteRequestResponse::dispatchSync(null, null, 2, 'dispatchSync');

        $this->assertEquals(0, config('request-response-logger.model')::count());
    }


    /**
     * @test
     */
    #[Test]
    public function the_cleaning_job_deletes_only_the_matching_records()
    {

        DB::table(config('request-response-logger.table'))
            ->insert([
                ['response_status_code' => '500', 'marked' => false, 'created_at' => now()->subHours(48)],
                ['response_status_code' => '502', 'marked' => true, 'created_at' => now()->subHours(48)],
            ]);

        DeleteRequestResponse::dispatchSync(24, false, 1, 'dispatchSync');

        $modelClass = config('request-response-logger.model');

        // only the old unmarked record is deleted
        $this->assertEquals(5, $modelClass::count());
        $this->assertEquals(0, $modelClass::where('response_status_code', '500')->count());
    }


    /**
     * @test
     */
    #[Test]
    public function the_queued_cleaning_job_stops_when_there_is_nothing_left_to_delete()
    {

        DB::table(config('request-response-logger.table'))->delete();

        Queue::fake();

        $job = new DeleteRequestResponse(null, null, 2, 'dispatch');
        $job->setJob(Mockery::mock(QueueJob::class));

        $job->handle();

        Queue::assertNothingPushed();
    }

}
