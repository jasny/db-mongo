<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Write\Traits;

use Jasny\DB\Exception\BuildQueryException;
use Jasny\DB\Mongo\Query\FilterQuery;
use Jasny\DB\Option\OptionInterface;
use Jasny\DB\QueryBuilder\FilterQueryBuilder;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\Result\Result;
use MongoDB\Collection;

/**
 * Delete data from a MongoDB collection.
 */
trait DeleteTrait
{
    /**
     * Get MongoDB collection object.
     */
    abstract public function getStorage(): Collection;

    /**
     * Get the query builder of updating items.
     *
     * @return QueryBuilderInterface
     */
    public function getDeleteQueryBuilder(): QueryBuilderInterface
    {
        $this->deleteQueryBuilder = (new FilterQueryBuilder());

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
     * Query and delete records.
     * The result will not contain any items, only meta data `count` with the number of deleted items.
     *
     * @param array             $filter
     * @param OptionInterface[] $opts
     * @return Result
     * @throws BuildQueryException
     */
    public function delete(array $filter, array $opts = []): Result
    {
        $query = new FilterQuery('delete');
        $this->getQueryBuilder()->apply($query, $filter, $opts);

        $method = $query->getExpectedMethod('deleteOne', 'deleteMany');
        $mongoFilter = $query->toArray();
        $mongoOptions = $query->getOptions();

        $this->debug("%s.$method", ['filter' => $mongoFilter, 'options' => $mongoOptions]);

        $deleteResult = $method === 'deleteOne'
            ? $this->getStorage()->deleteOne($mongoFilter, $mongoOptions)
            : $this->getStorage()->deleteMany($mongoFilter, $mongoOptions);

        $meta = $deleteResult->isAcknowledged() ? ['count' => $deleteResult->getDeletedCount()] : [];

        return new Result([], $meta);
    }
}
