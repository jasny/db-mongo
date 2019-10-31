<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder\Compose;

use Jasny\DB\Filter\FilterItem;
use Jasny\DB\Mongo\Query\FilterQuery;
use Jasny\DB\Option\OptionInterface;

/**
 * Standard logic to apply a filter item to a query.
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
     * @param FilterQuery       $query
     * @param FilterItem        $filterItem
     * @param OptionInterface[] $options
     */
    public function __invoke(FilterQuery $query, FilterItem $filterItem, array $options): void
    {
        [$field, $operator, $value] = [$filterItem->getField(), $filterItem->getOperator(), $filterItem->getValue()];

        if (!array_key_exists($operator, static::OPERATORS)) {
            throw new \UnexpectedValueException("Unsupported filter '{$operator}' operator for '{$field}'");
        }

        $mongoOperator = static::OPERATORS[$operator];
        $condition = $mongoOperator !== null ? [$field => [$mongoOperator => $value]] : [$field => $value];

        $query->add($condition);
    }
}
