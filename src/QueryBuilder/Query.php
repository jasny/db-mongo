<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Improved\IteratorPipeline\Pipeline;

/**
 * Accumulator for MongoDB query builder.
 * Only for 'filter' part of find, update and delete queries.
 */
class Query
{
    /**
     * @var array<string, mixed>
     */
    protected $options;

    /**
     * @var array<string, array>
     */
    protected $conditions = [];


    /**
     * Query constructor.
     *
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }


    /**
     * Set MongoDB specific option
     *
     * @param string $name
     * @param mixed $value
     */
    public function setOption(string $name, $value)
    {
        $this->options[$name] = $value;
    }

    /**
     * Add a condition to the query
     *
     * @param array $condition
     * @return void
     */
    public function add(array $condition): void
    {
        $this->conditions[] = $condition;
    }


    /**
     * Get MongoDB query options.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get MongoDB query filter.
     *
     * @return array
     */
    public function toArray(): array
    {
        $op = Pipeline::with($this->conditions)->flatten(true)->hasAny(function($value, string $key) {
            return $key[0] === '$';
        });

        return $op ? ['$and' => $this->conditions] : array_merge_recursive(...$this->conditions);
    }
}
