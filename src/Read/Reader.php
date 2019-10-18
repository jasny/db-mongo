<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Read;

use Improved as i;
use Jasny\DB\Exception\BuildQueryException;
use Jasny\DB\Mongo\Common\ReadWriteTrait;
use Jasny\DB\Mongo\Query\FilterQuery;
use Jasny\DB\Option\OptionInterface;
use Jasny\DB\Read\ReadInterface;
use Jasny\DB\Result\Result;
use MongoDB\Driver\Cursor;

/**
 * Fetch data from a MongoDB collection
 */
class Reader implements ReadInterface
{
    use ReadWriteTrait;

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
        $this->getQueryBuilder()->apply($query, $filter, $opts);

        $method = $query->getExpectedMethod('countDocuments', 'estimatedDocumentCount');
        $mongoFilter = $query->toArray();
        $mongoOptions = $query->getOptions();

        $this->debug("%s.$method", ['filter' => $mongoFilter, 'options' => $mongoOptions]);

        return $method === 'estimatedDocumentCount'
            ? $this->collection->estimatedDocumentCount($mongoOptions)
            : $this->collection->countDocuments($mongoFilter, $mongoOptions);
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
        $this->getQueryBuilder()->apply($query, $filter, $opts);

        $method = $query->getExpectedMethod('find', 'aggregate');
        $mongoFilter = $query->toArray();
        $mongoOptions = $query->getOptions();

        $this->debug("%s.$method", [
            ($method === 'aggregate' ? 'pipeline' : 'filter') => $mongoFilter,
            'options' => $mongoOptions
        ]);

        $cursor = $method === 'find'
            ? $this->getStorage()->find($mongoFilter, $mongoOptions)
            : $this->getStorage()->aggregate($mongoFilter, $mongoOptions);

        return $this->getResultBuilder()->with($cursor);
    }
}
