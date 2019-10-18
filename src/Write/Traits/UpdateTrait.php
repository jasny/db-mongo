<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Write\Traits;

use Improved\IteratorPipeline\Pipeline;
use Jasny\DB\Exception\BuildQueryException;
use Jasny\DB\Mongo\Query\UpdateQuery;
use Jasny\DB\Mongo\QueryBuilder\UpdateQueryBuilder;
use Jasny\DB\Option\OptionInterface;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\QueryBuilder\StagedQueryBuilder;
use Jasny\DB\Result\Result;
use Jasny\DB\Result\ResultBuilder;
use Jasny\DB\Update\UpdateOperation;
use MongoDB\BulkWriteResult;
use MongoDB\Collection;
use MongoDB\UpdateResult;

/**
 * Update data of a MongoDB collection.
 */
trait UpdateTrait
{
    protected QueryBuilderInterface $updateQueryBuilder;

    /**
     * Get MongoDB collection object.
     */
    abstract public function getStorage(): Collection;

    /**
     * Get the query builder.
     */
    abstract public function getQueryBuilder(): QueryBuilderInterface;

    /**
     * Get the result builder.
     */
    abstract public function getResultBuilder(): ResultBuilder;


    /**
     * Get the query builder of updating items.
     *
     * @return QueryBuilderInterface|StagedQueryBuilder
     */
    public function getUpdateQueryBuilder(): QueryBuilderInterface
    {
        $this->updateQueryBuilder ??= new UpdateQueryBuilder();

        return $this->updateQueryBuilder;
    }

    /**
     * Create a write with a custom query builder for save.
     *
     * @param QueryBuilderInterface $builder
     * @return static
     */
    public function withUpdateQueryBuilder(QueryBuilderInterface $builder): self
    {
        return $this->with('updateQueryBuilder', $builder);
    }


    /**
     * Query and update records.
     *
     * @param array             $filter
     * @param UpdateOperation[] $update
     * @param OptionInterface[] $opts
     * @return Result
     * @throws BuildQueryException
     */
    public function update(array $filter, array $update, array $opts = []): Result
    {
        $updateQuery = new UpdateQuery('update');

        $this->getQueryBuilder()->apply($updateQuery->getFilterQuery(), $filter, $opts);
        $this->getUpdateQueryBuilder()->apply($updateQuery, $update, $opts);

        $method = $updateQuery->getExpectedMethod('updateOne', 'updateMany');
        $mongoFilter = $updateQuery->getFilterQuery()->toArray();
        $mongoUpdate = $updateQuery->toArray();
        $mongoOptions = $updateQuery->getOptions();

        $this->debug("%s.$method", [
            'filter' => $mongoFilter,
            'update' => $mongoUpdate,
            'options' => $mongoOptions
        ]);

        /** @var UpdateResult|BulkWriteResult $writeResult */
        $writeResult = $method === 'updateOne'
            ? $this->getStorage()->updateOne($mongoFilter, $mongoUpdate, $mongoOptions)
            : $this->getStorage()->updateMany($mongoFilter, $mongoUpdate, $mongoOptions);

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

        return $this->getResultBuilder()->with($documents, $meta);
    }
}

