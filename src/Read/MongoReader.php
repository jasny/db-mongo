<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Read;

use Improved as i;
use Improved\IteratorPipeline\PipelineBuilder;
use InvalidArgumentException;
use Jasny\DB\Mongo\QueryBuilder\DefaultBuilders;
use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\QueryBuilder;
use Jasny\DB\Read;
use Jasny\DB\Result;
use MongoDB\Collection;
use UnexpectedValueException;

/**
 * Fetch data from a MongoDB collection
 */
class MongoReader implements Read, Read\WithBuilders
{
    /**
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * @var PipelineBuilder
     */
    protected $resultBuilder;


    /**
     * Get the query builder.
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder(): QueryBuilder
    {
        if (!isset($this->queryBuilder)) {
            $this->queryBuilder = DefaultBuilders::createFilterQueryBuilder();
        }

        return $this->queryBuilder;
    }

    /**
     * Create a reader with a custom query builder.
     *
     * @param QueryBuilder $queryBuilder
     * @return static
     */
    public function withQueryBuilder(QueryBuilder $queryBuilder): self
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
        i\type_check($storage, Collection::class, new InvalidArgumentException());

        /** @var Query $query */
        $query = i\type_check(
            $this->queryBuilder->buildQuery($filter ?? [], $opts),
            Query::class,
            new UnexpectedValueException()
        );

        return $storage->countDocuments($query->toArray(), $query->getOptions());
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
        i\type_check($storage, Collection::class, new InvalidArgumentException());

        /** @var Query $result */
        $query = i\type_check(
            $this->queryBuilder->buildQuery($filter ?? [], $opts),
            Query::class,
            new UnexpectedValueException()
        );

        $cursor = $storage->find($query->toArray(), $query->getOptions());

        /** @var Result $result */
        $result = i\type_check(
            $this->resultBuilder->with($cursor),
            Result::class,
            new UnexpectedValueException()
        );

        return $result;
    }
}
