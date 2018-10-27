<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Read;

use Improved\IteratorPipeline\PipelineBuilder;
use Jasny\DB\Mongo\QueryBuilder\DefaultBuilders;
use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\QueryBuilder\QueryBuilding;
use Jasny\DB\Read;
use Jasny\DB\Result;
use MongoDB\Collection;
use function Jasny\expect_type;

/**
 * Fetch data from a MongoDB collection
 */
class MongoReader implements Read, Read\WithBuilders
{
    /**
     * @var QueryBuilding
     */
    protected $queryBuilder;

    /**
     * @var PipelineBuilder
     */
    protected $resultBuilder;


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
     * Get the result builder.
     *
     * @return PipelineBuilder
     */
    public function getResultBuilder(): PipelineBuilder
    {
        if (!isset($this->resultBuilder)) {
            $this->resultBuilder = DefaultBuilders::createResultBuilder();
        }

        return $this->resultBuilder;
    }

    /**
     * Create a reader with a custom result builder.
     *
     * @param PipelineBuilder $resultBuilder
     * @return static
     */
    public function withResultBuilder(PipelineBuilder $resultBuilder): self
    {
        if ($this->resultBuilder === $resultBuilder) {
            return $this;
        }

        $clone = clone $this;
        $clone->resultBuilder = $resultBuilder;

        return $clone;
    }


    /**
     * Fetch the number of entities in the set.
     *
     * @param Collection $storage
     * @param array      $filter
     * @param array      $opts
     * @return int
     */
    public function count($storage, array $filter = null, array $opts = []): int
    {
        expect_type($storage, Collection::class, \InvalidArgumentException::class);

        $query = $this->queryBuilder->buildQuery($filter ?? [], $opts);
        expect_type($query, Query::class, \UnexpectedValueException::class);

        return $storage->count($query->toArray(), $query->getOptions());
    }

    /**
     * Query and fetch data.
     *
     * @param Collection $storage
     * @param array      $filter
     * @param array      $opts
     * @return Result
     */
    public function fetch($storage, array $filter = null, array $opts = []): Result
    {
        expect_type($storage, Collection::class, \InvalidArgumentException::class);

        /** @var Query $result */
        $query = $this->queryBuilder->buildQuery($filter ?? [], $opts);
        expect_type($query, Query::class, \UnexpectedValueException::class);

        $cursor = $storage->find($query->toArray(), $query->getOptions());

        /** @var Result $result */
        $result = $this->resultBuilder->with($cursor);
        expect_type($result, Result::class, \UnexpectedValueException::class);

        return $result;
    }
}
