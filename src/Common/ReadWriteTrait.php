<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Common;

use Jasny\DB\Mongo\QueryBuilder\FilterQueryBuilder;
use Jasny\DB\Mongo\Result\ResultBuilder as MongoResultBuilder;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\Result\ResultBuilder;
use MongoDB\Collection;
use Psr\Log\LoggerInterface;
use Jasny\Immutable;

/**
 * Common methods for Reader and Writer.
 */
trait ReadWriteTrait
{
    use Immutable\With;

    protected QueryBuilderInterface $queryBuilder;
    protected ResultBuilder $resultBuilder;

    protected Collection $collection;
    protected ?LoggerInterface $logger;


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
     * Enable (debug) logging.
     *
     * @return static
     */
    public function withLogging(LoggerInterface $logger): self
    {
        return $this->withProperty('logger', $logger);
    }

    /**
     * Log a debug message.
     *
     * @param string $message
     * @param array $context
     */
    protected function debug(string $message, array $context): void
    {
        if (isset($this->logger)) {
            $this->logger->debug(sprintf($message, $this->collection->getCollectionName()), $context);
        }
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
     * @param QueryBuilderInterface|StagedQueryBuilder $builder
     * @return static
     */
    public function withQueryBuilder(QueryBuilderInterface $builder): self
    {
        return $this->withProperty('queryBuilder', $builder);
    }

    /**
     * Get the result builder.
     */
    public function getResultBuilder(): ResultBuilder
    {
        $this->resultBuilder ??= new MongoResultBuilder();

        return $this->resultBuilder;
    }

    /**
     * Create a reader with a custom result builder.
     *
     * @param ResultBuilder $builder
     * @return static
     */
    public function withResultBuilder(ResultBuilder $builder): self
    {
        return $this->withProperty('resultBuilder', $builder);
    }
}
