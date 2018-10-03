<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

/**
 * Interface for service that can convert a filter to a MongoDB query.
 * @immutable
 */
interface QueryBuilderInterface
{
    /**
     * Create a closure for applying an alias.
     *
     * @param string $field
     * @return \Closure
     */
    public function alias(string $field): \Closure;

    /**
     * Create a query builder with a custom filter criteria.
     *
     * @param string   $key
     * @param callable $apply
     * @return static
     */
    public function with(string $key, callable $apply);

    /**
     * Convert a a filter into a query.
     *
     * @param iterable $filter
     * @return array
     */
    public function buildQuery(iterable $filter): array;
}
