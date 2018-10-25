<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Jasny\DB\Exception\InvalidFilterException;

/**
 * Standard compose step for filter query.
 */
class FilterComposer
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
     * Invoke the composer.
     *
     * @param iterable $iterable
     * @return \Generator
     */
    public function __invoke(iterable $iterable): \Generator
    {
        foreach ($iterable as $info => $value) {
            $info['value'] = $value;

            yield $info => [$this, 'apply'];
        }
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
    public function apply(Query $query, string $field, string $operator, $value): void
    {
        if ($field[0] === '$') {
            throw new InvalidFilterException("Invalid filter key '$field'. Starting with '$' isn't allowed.");
        }

        if (!array_key_exists($operator, self::OPERATORS)) {
            throw new InvalidFilterException("Invalid filter key '$field'. Unknown operator '$operator'.");
        }

        $mongoOperator = self::OPERATORS[$operator];
        $condition = isset($mongoOperator) ? [$field => [$mongoOperator => $value]] : [$field => $value];

        $query->add($condition);
    }

}