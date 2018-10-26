<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Write;

use Jasny\DB\Mongo\QueryBuilder\DefaultQueryBuilders;
use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\Write\WriterInterface;
use Jasny\DB\Result\Result;
use MongoDB\BSON;
use MongoDB\BulkWriteResult;
use MongoDB\Collection;
use function Jasny\expect_type;

/**
 * Fetch data from a MongoDB collection
 */
class MongoWriter implements WriterInterface
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
     * @var QueryBuilderInterface
     */
    protected $queryBuilder;

    /**
     * @var QueryBuilderInterface
     */
    protected $saveQueryBuilder;

    /**
     * @var QueryBuilderInterface
     */
    protected $updateQueryBuilder;


    /**
     * Get the query builder.
     *
     * @return QueryBuilderInterface
     */
    public function getQueryBuilder(): QueryBuilderInterface
    {
        if (!isset($this->queryBuilder)) {
            $this->queryBuilder = DefaultQueryBuilders::filter();
        }

        return $this->queryBuilder;
    }

    /**
     * Create a reader with a custom query builder.
     *
     * @param QueryBuilderInterface $queryBuilder
     * @return static
     */
    public function withQueryBuilder(QueryBuilderInterface $queryBuilder): self
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
     * @return QueryBuilderInterface
     */
    public function getSaveQueryBuilder(): QueryBuilderInterface
    {
        if (!isset($this->saveQueryBuilder)) {
            $this->saveQueryBuilder = DefaultQueryBuilders::save();
        }

        return $this->saveQueryBuilder;
    }

    /**
     * Create a reader with a custom query builder.
     *
     * @param QueryBuilderInterface $queryBuilder
     * @return static
     */
    public function withSaveQueryBuilder(QueryBuilderInterface $queryBuilder): self
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
     * @return QueryBuilderInterface
     */
    public function getUpdateQueryBuilder(): QueryBuilderInterface
    {
        if (!isset($this->updateQueryBuilder)) {
            $this->updateQueryBuilder = DefaultQueryBuilders::update();
        }

        return $this->updateQueryBuilder;
    }

    /**
     * Create a reader with a custom query builder.
     *
     * @param QueryBuilderInterface $queryBuilder
     * @return static
     */
    public function withUpdateQueryBuilder(QueryBuilderInterface $queryBuilder): self
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
     * @return Result
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
     * @return Result
     */
    protected function combineWriteResults(array $writeResults, array $counts): Result
    {
        $ids = [];
        $meta = self::SAVE_META;

        foreach ($writeResults as $i => $writeResult) {
            $ids[] = $writeResult->getInsertedIds()
                + $writeResult->getUpsertedIds()
                + array_fill(0, $counts[$i], null);

            $meta['deletedCount'] += $writeResult->getDeletedCount();
            $meta['insertedCount'] += $writeResult->getInsertedCount();
            $meta['matchedCount'] += $writeResult->getMatchedCount();
            $meta['modifiedCount'] += $writeResult->getModifiedCount();
            $meta['upsertedCount'] += $writeResult->getUpsertedCount();
            $meta['acknowledged'] = $meta['acknowledged'] && $writeResult->isAcknowledged();
        }

        return (new Result($ids, $meta))
            ->flatten()
            ->values()
            ->map(function(?BSON\ObjectId $id) {
                return isset($id) ? (string)$id : null;
            });
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

        $deleteResult = $storage->deleteMany($storage, $query->toArray(), $query->getOptions());

        $meta = [
            'deletedCount' => $deleteResult->getDeletedCount(),
            'acknowledged' => $deleteResult->isAcknowledged()
        ];

        return new Result([], $meta);
    }
}
