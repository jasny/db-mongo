<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder\Compose;

use Improved as i;
use Jasny\DB\Exception\InvalidFilterException;
use Jasny\DB\Mongo\Query\FilterQuery;

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
     */
    public function __invoke(iterable $iterable): \Generator
    {
        $callback = \Closure::fromCallable([$this, 'apply']);
        $exception = new \UnexpectedValueException("Excepted keys to be an array; %s given");

        foreach ($iterable as $info => $value) {
            i\type_check($info, 'array', $exception);
            $info['value'] = $value;

            yield $info => $callback;
        }
    }

    /**
     * Default logic to apply a filter criteria.
     *
     * @param FilterQuery $query
     * @param string      $field
     * @param string      $operator
     * @param mixed       $value
     * @return void
     */
    public function apply($query, string $field, string $operator, $value): void
    {
        i\type_check($query, FilterQuery::class);

        if (!array_key_exists($operator, static::OPERATORS)) {
            throw new \UnexpectedValueException("Unsupported filter '{$operator}' operator for '{$field}'");
        }

        $mongoOperator = static::OPERATORS[$operator];
        $condition = $mongoOperator !== null ? [$field => [$mongoOperator => $value]] : [$field => $value];

        $query->add($condition);
    }
}
