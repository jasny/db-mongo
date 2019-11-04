<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Query;

use Improved\IteratorPipeline\Pipeline;

/**
 * Representation of a MongoDB update query.
 * This object is mutable, as it's uses as accumulator by the query builders.
 */
class UpdateQuery implements QueryInterface
{
    protected FilterQuery $filterQuery;

    /** @var array<int, array<string, array>> */
    protected array $statements = [];

    /** @var array<string, mixed> */
    protected array $options = [];

    /**
     * Query constructor.
     */
    public function __construct(FilterQuery $filterQuery)
    {
        $this->filterQuery = $filterQuery;
    }

    /**
     * Get the filter part of the update query
     */
    public function getFilterQuery(): FilterQuery
    {
        return $this->filterQuery;
    }


    /**
     * Get the query method.
     */
    public function getMethod(): string
    {
        return $this->filterQuery->getMethod();
    }

    /**
     * Get the query method if it's one of the expected methods.
     *
     * @throws \UnexpectedValueException
     */
    public function getExpectedMethod(string ...$expected): string
    {
        return $this->filterQuery->getExpectedMethod(...$expected);
    }

    /**
     * Set the query method.
     */
    public function setMethod(string $method): void
    {
        $this->filterQuery->setMethod($method);
    }


    /**
     * Get MongoDB query options.
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Set MongoDB specific query option.
     *
     * @param string $name
     * @param mixed $value
     */
    public function setOption(string $name, $value): void
    {
        $this->options[$name] = $value;
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
