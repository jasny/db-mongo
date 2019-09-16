<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Read;

use Improved as i;
use Improved\IteratorPipeline\PipelineBuilder;
use Jasny\DB\Mongo\QueryBuilder\FilterQueryBuilder;
use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\Mongo\Result\ResultBuilder;
use Jasny\DB\Option\OptionInterface;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\Read\ReadInterface;
use Jasny\DB\Result;
use MongoDB\Collection;
use UnexpectedValueException;

/**
 * Fetch data from a MongoDB collection
 */
class MongoReader implements ReadInterface
{
    protected Collection $collection;

    protected QueryBuilderInterface $queryBuilder;
    protected PipelineBuilder $resultBuilder;


    /**
     * MongoWriter constructor.
     */
    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    /**
     * Get the mongodb collection the associated with the writer.
     *
     * @return Collection
     */
    public function getStorage(): Collection
    {
        return $this->collection;
    }


    /**
     * Create a copy with a modified property.
     *
     * @param string                                $prop
     * @param QueryBuilderInterface|PipelineBuilder $builder
     * @return static
     */
    protected function with(string $prop, $builder)
    {
        if (isset($this->{$prop}) && $this->{$prop} === $builder) {
            return $this;
        }

        $clone = clone $this;
        $clone->{$prop} = $builder;

        return $clone;
    }

    /**
     * Get the query builder.
     */
    public function getQueryBuilder(): QueryBuilderInterface
    {
        $this->queryBuilder ??= new FilterQueryBuilder();

        return $this->queryBuilder;
    }

    /**
     * Create a reader with a custom query builder.
     *
     * @param QueryBuilderInterface $builder
     * @return static
     */
    public function withQueryBuilder(QueryBuilderInterface $builder): ReadInterface
    {
        return $this->with('queryBuilder', $builder);
    }


    /**
     * Get the result builder.
     *
     * @return PipelineBuilder
     */
    public function getResultBuilder(): PipelineBuilder
    {
        $this->resultBuilder ??= new ResultBuilder();

        return $this->resultBuilder;
    }

    /**
     * Create a reader with a custom result builder.
     *
     * @param PipelineBuilder $builder
     * @return static
     */
    public function withResultBuilder(PipelineBuilder $builder): ReadInterface
    {
        return $this->with('resultBuilder', $builder);
    }


    /**
     * Fetch the number of entities in the set.
     *
     * @param array             $filter
     * @param OptionInterface[] $opts
     * @return int
     */
    public function count(array $filter = null, array $opts = []): int
    {
        /** @var Query $query */
        $query = i\type_check(
            $this->queryBuilder->buildQuery($filter ?? [], $opts),
            Query::class,
            new UnexpectedValueException()
        );

        return $this->getStorage()->countDocuments($query->toArray(), $query->getOptions());
    }

    /**
     * Query and fetch data.
     *
     * @param array             $filter
     * @param OptionInterface[] $opts
     * @return Result
     */
    public function fetch(array $filter = null, array $opts = []): Result
    {
        /** @var Query $result */
        $query = i\type_check(
            $this->queryBuilder->buildQuery($filter ?? [], $opts),
            Query::class,
            new UnexpectedValueException()
        );

        $cursor = $this->getStorage()->find($query->toArray(), $query->getOptions());

        /** @var Result $result */
        $result = $this->resultBuilder->with($cursor);

        return $result;
    }
}
