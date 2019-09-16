<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Write\Traits;

use Improved\IteratorPipeline\Pipeline;
use Jasny\DB\Mongo\QueryBuilder\SaveQueryBuilder;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\Result;
use Jasny\DB\Option\OptionInterface;
use Jasny\DB\Write\WriteInterface;
use MongoDB\BulkWriteResult;
use MongoDB\Collection;

/**
 * Save data to a MongoDB collection.
 */
trait SaveTrait
{
    /**
     * Get MongoDB collection object.
     */
    abstract public function getStorage(): Collection;

    /**
     * Combine multiple bulk write results into a single result.
     */
    abstract protected function createResult(array $ids, array $meta): Result;

    /**
     * Get the query builder for saving new items
     *
     * @return QueryBuilderInterface
     */
    public function getSaveQueryBuilder(): QueryBuilderInterface
    {
        $this->saveQueryBuilder ??= new SaveQueryBuilder();

        return $this->saveQueryBuilder;
    }

    /**
     * Create a reader with a custom query builder.
     *
     * @param QueryBuilderInterface $builder
     * @return static
     */
    public function withSaveQueryBuilder(QueryBuilderInterface $builder): WriteInterface
    {
        return $this->with('saveQueryBuilder', $builder);
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
        $meta = [
            'count' => 0,
            'deletedCount' => 0,
            'insertedCount' => 0,
            'matchedCount' => 0,
            'modifiedCount' => 0,
            'upsertedCount' => 0,
            'acknowledged' => true
        ];

        $ids = Pipeline::with($writeResults)
            ->apply(function (BulkWriteResult $result) use (&$meta) {
                $meta = $this->aggregateWriteResultMeta($meta, $result);
            })
            ->map(function (BulkWriteResult $result, int $i) use ($counts) {
                $ids = $result->getInsertedIds()
                    + $result->getUpsertedIds()
                    + array_fill(0, $counts[$i], null);
                ksort($ids);

                return $ids;
            })
            ->flatten()
            ->toArray();

        return $this->createResult($ids, $meta);
    }

    /**
     * Aggregate the meta from multiple bulk write actions
     */
    protected function aggregateWriteResultMeta(array $meta, BulkWriteResult $result): array
    {
        $meta['count'] += $result->getDeletedCount()
            + $result->getInsertedCount()
            + $result->getModifiedCount()
            + $result->getUpsertedCount();

        $meta['deletedCount'] += $result->getDeletedCount();
        $meta['insertedCount'] += $result->getInsertedCount();
        $meta['matchedCount'] += $result->getMatchedCount();
        $meta['modifiedCount'] += $result->getModifiedCount();
        $meta['upsertedCount'] += $result->getUpsertedCount();

        $meta['acknowledged'] = $meta['acknowledged'] && $result->isAcknowledged();

        return $meta;
    }

    /**
     * Save the data.
     * Returns an array with generated properties per entry.
     *
     * @param iterable          $items
     * @param OptionInterface[] $opts
     * @return Result&iterable<array>
     */
    public function save(iterable $items, array $opts = []): Result
    {
        $counts = [];
        $writeResults = [];

        $batches = $this->getSaveQueryBuilder()->buildQuery($items, $opts);

        foreach ($batches as $batch) {
            $counts[] = count($batch);
            $writeResults[] = $this->getStorage()->bulkWrite($batch, ['ordered' => false]);
        }

        return $this->combineWriteResults($writeResults, $counts);
    }
}
