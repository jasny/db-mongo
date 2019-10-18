<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Query;

use Improved\IteratorPipeline\Pipeline;

/**
 * Representation of a MongoDB update query.
 * This object is mutable, as it's uses as accumulator by the query builders.
 */
class UpdateQuery extends AbstractQuery
{
    protected FilterQuery $filterQuery;

    /** @var array<int, array<string, array>> */
    protected array $statements = [];


    /**
     * Get the filter part of the update query
     */
    public function getFilterQuery(): FilterQuery
    {
        return $this->filterQuery;
    }

    /**
     * Add a statement to the query.
     *
     * @param array<string, mixed> $statement
     */
    public function add(array $statement): void
    {
        $this->statements[] = $statement;
    }

    /**
     * Loop through all statements, replacing them with the return value of the callback
     *
     * @param callable $callable
     */
    public function map(callable $callable): void
    {
        foreach ($this->statements as &$statement) {
            $statement = $callable($statement);
        }
    }


    /**
     * Get MongoDB query statements.
     */
    public function toArray(): array
    {
        return Pipeline::with($this->statements)
            ->flatten(true)
            ->group(fn($_, string $key) => $key)
            ->map(fn(array $value, string $key) => ($key[0] === '$' ? array_merge(...$value) : end($value)))
            ->toArray();
    }
}
