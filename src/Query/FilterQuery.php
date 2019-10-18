<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Query;

use Improved\IteratorPipeline\Pipeline;

/**
 * Generic representation of a MongoDB query.
 * This object is mutable, as it's uses as accumulator by the query builders.
 */
class FilterQuery extends AbstractQuery
{
    /** @var array<int, array<string, array>> */
    protected array $statements = [];


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
        $hasOr = Pipeline::with($this->statements)
            ->flatten(true)
            ->hasAny(fn($_, string $key) => $key === '$or');

        return $hasOr ? ['$and' => $this->statements] : $this->getMergedStatements();
    }

    /**
     * Get all statements merged.
     */
    protected function getMergedStatements(): array
    {
        return Pipeline::with($this->statements)
            ->flatten(true)
            ->group(fn($_, string $key) => $key)
            ->map(fn(array $value, string $key) => ($key[0] === '$' ? array_merge(...$value) : end($value)))
            ->toArray();
    }
}
