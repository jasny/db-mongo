<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Traits;

use Jasny\DB\Exception\BuildQueryException;
use Jasny\DB\Mongo\Query\FilterQuery;
use Jasny\DB\Option\OptionInterface;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\Result\Result;
use MongoDB\Collection;

/**
 * Read data from a MongoDB collection.
 */
trait ReadTrait
{
    protected QueryBuilderInterface $queryBuilder;

    /**
     * Get the mongodb collection the associated with the service.
     */
    abstract public function getCollection(): Collection;

    /**
     * Create a result.
     */
    abstract protected function createResult(iterable $cursor, array $meta = []): Result;

    /**
     * Fetch the number of entities in the set.
     *
     * @param array             $filter
     * @param OptionInterface[] $opts
     * @return int
     * @throws BuildQueryException
     */
    public function count(array $filter = [], array $opts = []): int
    {
        $query = new FilterQuery('countDocuments');
        $this->queryBuilder->apply($query, $filter, $opts);

        $method = $query->getExpectedMethod('countDocuments', 'estimatedDocumentCount');
        $mongoFilter = $query->toArray();
        $mongoOptions = $query->getOptions();

        $this->debug("%s.$method", ['filter' => $mongoFilter, 'options' => $mongoOptions]);

        return $method === 'estimatedDocumentCount'
            ? $this->getCollection()->estimatedDocumentCount($mongoOptions)
            : $this->getCollection()->countDocuments($mongoFilter, $mongoOptions);
    }

    /**
     * Query and fetch data.
     *
     * @param array             $filter
     * @param OptionInterface[] $opts
     * @return Result
     * @throws BuildQueryException
     */
    public function fetch(array $filter = [], array $opts = []): Result
    {
        $query = new FilterQuery('find');
        $this->queryBuilder->apply($query, $filter, $opts);

        $method = $query->getExpectedMethod('find', 'aggregate');
        $mongoFilter = $query->toArray();
        $mongoOptions = $query->getOptions();

        $this->debug("%s.$method", [
            ($method === 'aggregate' ? 'pipeline' : 'filter') => $mongoFilter,
            'options' => $mongoOptions
        ]);

        $cursor = $method === 'find'
            ? $this->getCollection()->find($mongoFilter, $mongoOptions)
            : $this->getCollection()->aggregate($mongoFilter, $mongoOptions);

        return $this->createResult($cursor);
    }
}
