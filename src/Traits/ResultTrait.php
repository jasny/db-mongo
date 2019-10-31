<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Traits;

use Jasny\DB\Result\Result;
use Jasny\DB\Result\ResultBuilder;
use MongoDB\Driver\Cursor;

/**
 * Result builder for read and write service.
 */
trait ResultTrait
{
    protected ResultBuilder $resultBuilder;

    /**
     * Create a result.
     */
    protected function createResult(iterable $cursor, array $meta = []): Result
    {
        return $this->resultBuilder->with($cursor, $meta);
    }
}
