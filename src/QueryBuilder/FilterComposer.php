<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Jasny\DB\Exception\InvalidFilterException;

/**
 * Standard compose step for filter query.
 */
class FilterComposer extends AbstractComposer
{
    /**
     * Default operator conversion
     */
    protected const OPERATORS = [
        '' => null,
        'not' => '$ne',
        'min' => '$gte',
        'max' => '$lte',
        'any' => '$in',
        'none' => '$nin',
        'all' => '$all'
    ];


    /**
     * Default logic to apply a filter criteria.
     *
     * @param Query  $query
     * @param string $field
     * @param string $operator
     * @param mixed  $value
     * @return void
     * @throws InvalidFilterException
     */
    protected function apply(Query $query, string $field, string $operator, $value): void
    {
        $this->assert($field, $operator, $value);

        $mongoOperator = static::OPERATORS[$operator];
        $condition = isset($mongoOperator) ? [$field => [$mongoOperator => $value]] : [$field => $value];

        $query->add($condition);
    }
}
