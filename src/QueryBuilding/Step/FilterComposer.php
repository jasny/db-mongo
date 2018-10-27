<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilding\Step;

use Jasny\DB\Exception\InvalidFilterException;
use Jasny\DB\Mongo\QueryBuilding\Query;

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
     * Create a custom invalid argument exception.
     *
     * @param string $message
     * @return InvalidFilterException
     */
    protected function invalid(string $message): \InvalidArgumentException
    {
        return new InvalidFilterException($message);
    }


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
