<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Read;

use Improved\IteratorPipeline\PipelineBuilder;
use Jasny\DB\Mongo\QueryBuilder\QueryBuilderFactory;
use Jasny\DB\Mongo\TypeConversion\CastToPHP;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\Read\ReaderInterface;
use Jasny\DB\Read\Result;
use MongoDB\Collection;
use function Jasny\expect_type;

/**
 * Fetch data from a MongoDB collection
 */
class MongoReader implements ReaderInterface
{
    /**
     * @var QueryBuilderInterface
     */
    protected $queryBuilder;

    /**
     * @var PipelineBuilder
     */
    protected $resultBuilder;


    /**
     * Get the query builder.
     *
     * @return QueryBuilderInterface
     */
    public function getQueryBuilder(): QueryBuilderInterface
    {
        if (!isset($this->queryBuilder)) {
            $this->queryBuilder = new QueryBuilderFactory();
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
     * Get the result builder.
     *
     * @return PipelineBuilder
     */
    public function getResultBuilder(): PipelineBuilder
    {
        if (!isset($this->resultBuilder)) {
            $this->resultBuilder = (new PipelineBuilder)
                ->then(function (iterable $iterable) {
                    return new Result($iterable);
                })
                ->then(new FieldMap(['_id' => 'id']))
                ->then(new CastToPHP());
        }

        return $this->resultBuilder;
    }

    /**
     * Create a reader with a custom result builder.
     *
     * @param PipelineBuilder $resultBuilder
     * @return mixed
     */
    public function withResultBuilder(PipelineBuilder $resultBuilder)
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
        expect_type($storage, Collection::class);

        $query = $this->queryBuilder->buildQuery($filter ?? [], $opts);
        expect_type($query, Query::class, \UnexpectedValueException::class);

        return $storage->count($query->getConditions(), $query->getOptions());
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
        expect_type($storage, Collection::class);

        $query = $this->queryBuilder->buildQuery($filter ?? [], $opts);
        expect_type($query, Query::class, \UnexpectedValueException::class);

        $cursor = $storage->find($query->getConditions(), $query->getOptions());

        $result = $this->resultBuilder->with($cursor);
        expect_type($query, Result::class, \UnexpectedValueException::class);

        return $result;
    }
}
