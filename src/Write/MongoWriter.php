<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Write;

use Improved\IteratorPipeline\Pipeline;
use Improved\IteratorPipeline\PipelineBuilder;
use Jasny\DB\Exception\InvalidOptionException;
use Jasny\DB\Mongo\QueryBuilder\DefaultBuilders;
use Jasny\DB\QueryBuilder;
use Jasny\DB\Write;
use Jasny\DB\Result;
use Jasny\DB\Option;
use Jasny\DB\Option\LimitOption;
use function Jasny\expect_type;

/**
 * Fetch data from a MongoDB collection
 */
class MongoWriter implements Write, Write\WithBuilders
{
    use Traits\SaveTrait;
    use Traits\UpdateTrait;
    use Traits\DeleteTrait;

    /**
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * @var QueryBuilder
     */
    protected $saveQueryBuilder;

    /**
     * @var QueryBuilder
     */
    protected $updateQueryBuilder;

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
     * Get the query builder for saving new items
     *
     * @return QueryBuilder
     */
    public function getSaveQueryBuilder(): QueryBuilder
    {
        if (!isset($this->saveQueryBuilder)) {
            $this->saveQueryBuilder = DefaultBuilders::createSaveQueryBuilder();
        }

        return $this->saveQueryBuilder;
    }

    /**
     * Create a reader with a custom query builder.
     *
     * @param QueryBuilder $queryBuilder
     * @return static
     */
    public function withSaveQueryBuilder(QueryBuilder $queryBuilder): self
    {
        if ($this->saveQueryBuilder === $queryBuilder) {
            return $this;
        }

        $clone = clone $this;
        $clone->saveQueryBuilder = $queryBuilder;

        return $clone;
    }


    /**
     * Get the query builder of updating items.
     *
     * @return QueryBuilder
     */
    public function getUpdateQueryBuilder(): QueryBuilder
    {
        if (!isset($this->updateQueryBuilder)) {
            $this->updateQueryBuilder = DefaultBuilders::createUpdateQueryBuilder();
        }

        return $this->updateQueryBuilder;
    }

    /**
     * Create a reader with a custom query builder.
     *
     * @param QueryBuilder $queryBuilder
     * @return static
     */
    public function withUpdateQueryBuilder(QueryBuilder $queryBuilder): self
    {
        if ($this->updateQueryBuilder === $queryBuilder) {
            return $this;
        }

        $clone = clone $this;
        $clone->updateQueryBuilder = $queryBuilder;

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
     * Combine multiple bulk write results into a single result.
     *
     * @param array $ids
     * @param array $meta
     * @return Result&iterable<array>
     */
    protected function createResult(array $ids, array $meta): Result
    {
        $documents = Pipeline::with($ids)
            ->cleanup()
            ->map(function($id) {
                return ['_id' => $id];
            });

        /** @var Result $result */
        $result = $this->getResultBuilder()->with($documents);
        expect_type($result, Result::class, \UnexpectedValueException::class);

        return $result->withMeta($meta);
    }

    /**
     * Check limit to select 'One' or 'Many' variant of method.
     *
     * @param string   $method
     * @param Option[] $opts
     * @return void
     */
    protected function oneOrMany(string $method, array $opts)
    {
        /** @var LimitOption|null $limit */
        $limit = Pipeline::with($opts)
            ->filter(function($opt) {
                return $opt instanceof LimitOption;
            })
            ->last();

        if (isset($limit) && $limit->getLimit() !== 1) {
            $msg = "MongoDB can $method one document or all documents, but not exactly " . $limit->getLimit();
            throw new InvalidOptionException($msg);
        }

        return $method . (isset($limit) ? 'One' : 'Many');
    }
}
