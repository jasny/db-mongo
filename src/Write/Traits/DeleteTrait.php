<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Write\Traits;

use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\QueryBuilder;
use Jasny\DB\Result;
use Jasny\DB\Option;
use MongoDB\Collection;
use function Jasny\expect_type;

/**
 * Delete data from a MongoDB collection.
 */
trait DeleteTrait
{
    /**
     * Get the query builder.
     *
     * @return QueryBuilder
     */
    abstract public function getQueryBuilder(): QueryBuilder;

    /**
     * Combine multiple bulk write results into a single result.
     *
     * @param array $ids
     * @param array $meta
     * @return Result&iterable<array>
     */
    abstract protected function createResult(array $ids, array $meta): Result;

    /**
     * Check limit to select 'One' or 'Many' variant of method.
     *
     * @param string   $method
     * @param Option[] $opts
     * @return void
     */
    abstract protected function oneOrMany(string $method, array $opts);


    /**
     * Query and delete records.
     *
     * @param Collection $storage
     * @param array      $filter
     * @param Option[]   $opts
     * @return Result
     */
    public function delete($storage, array $filter, array $opts = []): Result
    {
        $query = $this->getQueryBuilder()->buildQuery($filter, $opts);
        expect_type($query, Query::class, \UnexpectedValueException::class);

        $method = $this->oneOrMany('delete', $opts);
        $deleteResult = $storage->$method($query->toArray(), $query->getOptions());

        $meta = [
            'count' => $deleteResult->getDeletedCount(),
            'deletedCount' => $deleteResult->getDeletedCount(),
            'acknowledged' => $deleteResult->isAcknowledged()
        ];

        return $this->createResult([], $meta);
    }
}
