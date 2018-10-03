<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Jasny\IteratorPipeline\Pipeline;
use function Jasny\str_starts_with;

/**
 * A MongoDB query as object
 */
class Query
{
    /**
     * @var array
     */
    protected $conditions = [];

    /**
     * Add condition to the query
     *
     * @param array $condition
     */
    public function add(array $condition)
    {
        $this->conditions[] = $condition;
    }

    /**
     * Does query has any logic operators
     *
     * @return bool
     */
    protected function hasLogicOperators(): bool
    {
        return Pipeline::with($this->conditions)
            ->flatten()
            ->keys()
            ->hasAny(function($key) {
                return str_starts_with($key, '$');
            });
    }

    /**
     * Cast the query to an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->hasLogicOperators()
            ? ['$and' => $this->conditions]
            : array_merge_recursive(...$this->conditions);
    }
}
