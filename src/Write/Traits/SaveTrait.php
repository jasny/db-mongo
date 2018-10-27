<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Write\Traits;

use Improved\IteratorPipeline\Pipeline;
use Jasny\DB\QueryBuilder;
use Jasny\DB\Result;
use Jasny\DB\Option;
use MongoDB\BulkWriteResult;
use MongoDB\Collection;

/**
 * Save data to a MongoDB collection.
 */
trait SaveTrait
{
    /**
     * Get the query builder for saving new items
     *
     * @return QueryBuilder
     */
    abstract public function getSaveQueryBuilder(): QueryBuilder;

    /**
     * Combine multiple bulk write results into a single result.
     *
     * @param array $ids
     * @param array $meta
     * @return Result&iterable<array>
     */
    abstract protected function createResult(array $ids, array $meta): Result;


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

        $sets = Pipeline::with($writeResults)
            ->apply(function(BulkWriteResult $writeResult) use (&$meta) {
                $meta['count'] += $writeResult->getDeletedCount() + $writeResult->getInsertedCount()
                    + $writeResult->getModifiedCount() + $writeResult->getUpsertedCount();
                $meta['deletedCount'] += $writeResult->getDeletedCount();
                $meta['insertedCount'] += $writeResult->getInsertedCount();
                $meta['matchedCount'] += $writeResult->getMatchedCount();
                $meta['modifiedCount'] += $writeResult->getModifiedCount();
                $meta['upsertedCount'] += $writeResult->getUpsertedCount();
                $meta['acknowledged'] = $meta['acknowledged'] && $writeResult->isAcknowledged();
            })
            ->map(function(BulkWriteResult $writeResult, int $i) use ($counts) {
                $ids = $writeResult->getInsertedIds()
                    + $writeResult->getUpsertedIds()
                    + array_fill(0, $counts[$i], null);
                ksort($ids);

                return $ids;
            })
            ->toArray();

        return $this->createResult(array_merge(...$sets), $meta);
    }

    /**
     * Save the data.
     * Returns an array with generated properties per entry.
     *
     * @param Collection $storage
     * @param iterable   $items
     * @param Option[]   $opts
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
}
