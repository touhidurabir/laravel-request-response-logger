<?php

namespace Touhidurabir\RequestResponseLogger\Tests\App\Jobs;

use RuntimeException;
use Touhidurabir\RequestResponseLogger\Jobs\StoreRequestResponse;

class FailingBatchStoreRequestResponse extends StoreRequestResponse
{
    /**
     * Fail on the batch store, like a database failure while moving the logs
     * from redis to the table
     *
     * @return void
     */
    public function handle(): void
    {

        if ($this->batch) {

            throw new RuntimeException('The batch store failed');
        }

        parent::handle();
    }
}
