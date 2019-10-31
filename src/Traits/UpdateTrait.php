<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Traits;

use Improved\IteratorPipeline\Pipeline;
use Jasny\DB\Exception\BuildQueryException;
use Jasny\DB\Mongo\Query\FilterQuery;
use Jasny\DB\Mongo\Query\UpdateQuery;
use Jasny\DB\Option\OptionInterface;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\Result\Result;
use Jasny\DB\Update\UpdateInstruction;
use MongoDB\BulkWriteResult;
use MongoDB\Collection;
use MongoDB\UpdateResult;

/**
 * Update data of a MongoDB collection.
 */
trait UpdateTrait
{
    protected QueryBuilderInterface $queryBuilder;
    protected QueryBuilderInterface $updateQueryBuilder;

    /**
     * Get MongoDB collection object.
     */
    abstract public function getCollection(): Collection;

    /**
     * Create a result.
     */
    abstract protected function createResult(iterable $cursor, array $meta = []): Result;


    /**
     * Query and update records.
     *
     * @param array               $filter
     * @param UpdateInstruction[] $update
     * @param OptionInterface[]   $opts
     * @return Result
     * @throws BuildQueryException
     */
    public function update(array $filter, array $update, array $opts = []): Result
    {
        $filterQuery = new FilterQuery('update');
        $updateQuery = new UpdateQuery($filterQuery);

        $this->queryBuilder->apply($filterQuery, $filter, $opts);
        $this->updateQueryBuilder->apply($updateQuery, $update, $opts);

        $method = $updateQuery->getExpectedMethod('updateOne', 'updateMany');
        $mongoFilter = $filterQuery->toArray();
        $mongoUpdate = $updateQuery->toArray();
        $mongoOptions = $updateQuery->getOptions();

        $this->debug("%s.$method", [
            'filter' => $mongoFilter,
            'update' => $mongoUpdate,
            'options' => $mongoOptions
        ]);

        /** @var UpdateResult|BulkWriteResult $writeResult */
        $writeResult = $method === 'updateOne'
            ? $this->getCollection()->updateOne($mongoFilter, $mongoUpdate, $mongoOptions)
            : $this->getCollection()->updateMany($mongoFilter, $mongoUpdate, $mongoOptions);

        return $this->createUpdateResult($writeResult);
    }

    /**
     * @param UpdateResult|BulkWriteResult $writeResult
     * @return Result
     */
    protected function createUpdateResult($writeResult): Result
    {
        $meta = $writeResult->isAcknowledged() ? [
            'count' => (int)$writeResult->getModifiedCount() + $writeResult->getUpsertedCount(),
            'matched' => $writeResult->getMatchedCount(),
            'modified' => $writeResult->getModifiedCount(),
            'upserted' => $writeResult->getUpsertedCount(),
        ] : [];

        $ids = $writeResult instanceof BulkWriteResult
            ? $writeResult->getUpsertedIds()
            : [$writeResult->getUpsertedId()];

        $documents = Pipeline::with($ids)
            ->filter(fn($id) => $id !== null)
            ->map(fn($id) => ['_id' => $id]);

        return $this->createResult($documents, $meta);
    }
}
