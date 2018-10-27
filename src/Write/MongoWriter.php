<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Write;

use Improved\IteratorPipeline\Pipeline;
use Jasny\DB\Mongo\QueryBuilding\DefaultBuilders;
use Jasny\DB\Mongo\QueryBuilding\Query;
use Jasny\DB\QueryBuilding;
use Jasny\DB\Update\UpdateOperation;
use Jasny\DB\Write;
use Jasny\DB\Result;
use MongoDB\BSON;
use MongoDB\BulkWriteResult;
use MongoDB\Collection;
use function Jasny\expect_type;

/**
 * Fetch data from a MongoDB collection
 */
class MongoWriter implements Write, Write\WithBuilders
{
    /**
     * Meta data for save action
     */
    protected const SAVE_META = [
        'deletedCount' => 0,
        'insertedCount' => 0,
        'matchedCount' => 0,
        'modifiedCount' => 0,
        'upsertedCount' => 0,
        'acknowledged' => true
    ];


    /**
     * @var QueryBuilding
     */
    protected $queryBuilder;

    /**
     * @var QueryBuilding
     */
    protected $saveQueryBuilder;

    /**
     * @var QueryBuilding
     */
    protected $updateQueryBuilder;


    /**
     * Get the query builder.
     *
     * @return QueryBuilding
     */
    public function getQueryBuilder(): QueryBuilding
    {
        if (!isset($this->queryBuilder)) {
            $this->queryBuilder = DefaultBuilders::createFilterQueryBuilder();
        }

        return $this->queryBuilder;
    }

    /**
     * Create a reader with a custom query builder.
     *
     * @param QueryBuilding $queryBuilder
     * @return static
     */
    public function withQueryBuilder(QueryBuilding $queryBuilder): self
    {
        if ($this->queryBuilder === $queryBuilder) {
            return $this;
        }

        $clone = clone $this;
        $clone->queryBuilder = $queryBuilder;

        return $clone;
    }


    /**
     * Get the query builder for saving new items
     *
     * @return QueryBuilding
     */
    public function getSaveQueryBuilder(): QueryBuilding
    {
        if (!isset($this->saveQueryBuilder)) {
            $this->saveQueryBuilder = DefaultBuilders::createSaveQueryBuilder();
        }

        return $this->saveQueryBuilder;
    }

    /**
     * Create a reader with a custom query builder.
     *
     * @param QueryBuilding $queryBuilder
     * @return static
     */
    public function withSaveQueryBuilder(QueryBuilding $queryBuilder): self
    {
        if ($this->saveQueryBuilder === $queryBuilder) {
            return $this;
        }

        $clone = clone $this;
        $clone->saveQueryBuilder = $queryBuilder;

        return $clone;
    }


    /**
     * Get the query builder of updating items.
     *
     * @return QueryBuilding
     */
    public function getUpdateQueryBuilder(): QueryBuilding
    {
        if (!isset($this->updateQueryBuilder)) {
            $this->updateQueryBuilder = DefaultBuilders::createUpdateQueryBuilder();
        }

        return $this->updateQueryBuilder;
    }

    /**
     * Create a reader with a custom query builder.
     *
     * @param QueryBuilding $queryBuilder
     * @return static
     */
    public function withUpdateQueryBuilder(QueryBuilding $queryBuilder): self
    {
        if ($this->updateQueryBuilder === $queryBuilder) {
            return $this;
        }

        $clone = clone $this;
        $clone->updateQueryBuilder = $queryBuilder;

        return $clone;
    }


    /**
     * Save the data.
     * Returns an array with generated properties per entry.
     *
     * @param Collection $storage
     * @param iterable   $items
     * @param array      $opts
     * @return Result&iterable<array>
     */
    public function save($storage, iterable $items, array $opts = []): Result
    {
        $counts = [];
        $writeResults = [];

        $batches = $this->getSaveQueryBuilder()->buildQuery($items, $opts);

        foreach ($batches as $batch) {
            $counts[] = count($batch);
            $writeResults[] = $storage->bulkWrite($batch, ['ordered' => false]);
        }

        return $this->combineWriteResults($writeResults, $counts);
    }

    /**
     * Combine multiple bulk write results into a single result.
     *
     * @param BulkWriteResult[] $writeResults
     * @param int[]             $counts
     * @return Result&iterable<array>
     */
    protected function combineWriteResults(array $writeResults, array $counts): Result
    {
        $meta = self::SAVE_META;

        $ids = Pipeline::with($writeResults)
            ->apply(function(BulkWriteResult $writeResult) use (&$meta) {
                $meta['deletedCount'] += $writeResult->getDeletedCount();
                $meta['insertedCount'] += $writeResult->getInsertedCount();
                $meta['matchedCount'] += $writeResult->getMatchedCount();
                $meta['modifiedCount'] += $writeResult->getModifiedCount();
                $meta['upsertedCount'] += $writeResult->getUpsertedCount();
                $meta['acknowledged'] = $meta['acknowledged'] && $writeResult->isAcknowledged();
            })
            ->map(function(BulkWriteResult $writeResult, int $i) use ($counts) {
                return $writeResult->getInsertedIds()
                    + $writeResult->getUpsertedIds()
                    + array_fill(0, $counts[$i], null);
            })
            ->flatten()
            ->map(function(?BSON\ObjectId $id) {
                return isset($id) ? ['_id' => (string)$id] : [];
            })
            ->toArray();

        return new Result($ids, $meta);
    }


    /**
     * Query and update records.
     *
     * @param Collection                        $storage
     * @param array                             $filter
     * @param UpdateOperation|UpdateOperation[] $changes
     * @param array                             $opts
     * @return Result
     */
    public function update($storage, array $filter, $changes, array $opts = []): Result
    {
        $filterQuery = $this->getQueryBuilder()->buildQuery($filter, $opts);
        expect_type($filterQuery, Query::class, \UnexpectedValueException::class);

        $updateQuery = $this->getUpdateQueryBuilder()->buildQuery($filter, $opts);
        expect_type($updateQuery, Query::class, \UnexpectedValueException::class);

        $options = $updateQuery->getOptions() + $filterQuery->getOptions();

        $updateResult = $storage->updateMany($filterQuery->toArray(), $updateQuery->toArray(), $options);

        $meta = [
            'matchedCount' => $updateResult->getMatchedCount(),
            'modifiedCount' => $updateResult->getModifiedCount(),
            'upsertedCount' => $updateResult->getUpsertedCount(),
            'acknowledged' => $updateResult->isAcknowledged()
        ];

        return new Result([], $meta);
    }


    /**
     * Query and delete records.
     *
     * @param Collection $storage
     * @param array      $filter
     * @param array      $opts
     * @return Result
     */
    public function delete($storage, array $filter, array $opts = []): Result
    {
        $query = $this->getQueryBuilder()->buildQuery($filter, $opts);
        expect_type($query, Query::class, \UnexpectedValueException::class);

        $deleteResult = $storage->deleteMany($query->toArray(), $query->getOptions());

        $meta = [
            'deletedCount' => $deleteResult->getDeletedCount(),
            'acknowledged' => $deleteResult->isAcknowledged()
        ];

        return new Result([], $meta);
    }
}
