<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Fetcher;

use Jasny\DB\Fetcher\FetcherInterface;

/**
 * Fetch data from a MongoDB collection
 */
class Fetcher implements FetcherInterface
{
    /**
     * @var
     */
    protected $builder;


    /**
     * Fetch data and make it available as iterable.
     *
     * @param array $filter Filter parameters
     * @param array $opts
     * @return iterable
     */
    public function fetchData(array $filter = [], array $opts = [])
    {
        // TODO: Implement fetchData() method.
    }

    /**
     * Fetch the number of entities in the set.
     *
     * @param array $filter
     * @param array $opts
     * @return int
     */
    public function count(array $filter = [], array $opts = []): int
    {
        // TODO: Implement count() method.
    }
}
