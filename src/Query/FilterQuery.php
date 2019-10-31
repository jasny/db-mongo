<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Query;

use Improved\IteratorPipeline\Pipeline;

/**
 * Generic representation of a MongoDB query.
 * This object is mutable, as it's uses as accumulator by the query builders.
 */
class FilterQuery implements QueryInterface
{
    protected string $method;

    /** @var array<int, array<string, array>> */
    protected array $statements = [];

    /** @var array<string, mixed> */
    protected array $options;

    /**
     * Query constructor.
     *
     * @param string               $method
     * @param array<string, mixed> $options
     */
    public function __construct(string $method, array $options = [])
    {
        $this->method = $method;
        $this->options = $options;
    }

    /**
     * Get the query method.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the query method if it's one of the expected methods.
     *
     * @throws \UnexpectedValueException
     */
    public function getExpectedMethod(string ...$expected): string
    {
        if (!in_array($this->method, $expected, true)) {
            throw new \UnexpectedValueException("Unexpected query method '{$this->method}', "
                . "should be one of " . join(',', $expected));
        }

        return $this->method;
    }

    /**
     * Set the query method.
     */
    public function setMethod(string $method): void
    {
        $this->method = $method;
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
