<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Write;

use Improved as i;
use Improved\IteratorPipeline\Pipeline;
use Improved\IteratorPipeline\PipelineBuilder;
use Jasny\DB\Exception\InvalidOptionException;
use Jasny\DB\Mongo\QueryBuilder\FilterQueryBuilder;
use Jasny\DB\Mongo\Result\ResultBuilder;
use Jasny\DB\Option\OptionInterface;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\Result;
use Jasny\DB\Option\LimitOption;
use Jasny\DB\Write\WriteInterface;
use MongoDB\Collection;
use UnexpectedValueException;

/**
 * Fetch data from a MongoDB collection
 */
class MongoWriter implements WriteInterface
{
    use Traits\SaveTrait;
    use Traits\UpdateTrait;
    use Traits\DeleteTrait;

    protected Collection $collection;

    protected QueryBuilderInterface $queryBuilder;
    protected QueryBuilderInterface $saveQueryBuilder;
    protected QueryBuilderInterface $updateQueryBuilder;
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
     * Create a writer with a custom query builder.
     *
     * @param QueryBuilderInterface $builder
     * @return static
     */
    public function withQueryBuilder(QueryBuilderInterface $builder): WriteInterface
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
    public function withResultBuilder(PipelineBuilder $builder): WriteInterface
    {
        return $this->with('resultBuilder', $builder);
    }


    /**
     * Combine multiple bulk write results into a single result.
     */
    protected function createResult(array $ids, array $meta): Result
    {
        /** @var Result $result */
        $result = Pipeline::with($ids)
            ->cleanup()
            ->map(fn($id) => ['_id' => $id])
            ->then(fn($documents) => $this->getResultBuilder()->with($documents));

        i\type_check($result, Result::class, new UnexpectedValueException());

        return $result->withMeta($meta);
    }

    /**
     * Check limit to select 'One' or 'Many' variant of method.
     *
     * @param string            $method
     * @param OptionInterface[] $opts
     * @return string
     */
    protected function oneOrMany(string $method, array $opts): \Closure
    {
        /** @var LimitOption|null $limit */
        $limit = Pipeline::with($opts)
            ->filter(fn($opt) => $opt instanceof LimitOption)
            ->last();

        if (isset($limit) && $limit->getLimit() !== 1) {
            $msg = "MongoDB can $method one document or all documents, but not exactly " . $limit->getLimit();
            throw new InvalidOptionException($msg);
        }

        $method .= (isset($limit) ? 'One' : 'Many');

        return \Closure::fromCallable([$this->getStorage(), $method]);
    }
}
