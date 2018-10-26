<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use MongoDB\BSON;
use Jasny\DB\Exception\InvalidFilterException;
use function Jasny\expect_type;

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
        $callback = \Closure::fromCallable([$this, 'apply']);

        foreach ($iterable as $info => $value) {
            expect_type($info, 'array', \UnexpectedValueException::class);
            $info['value'] = $value;

            yield $info => $callback;
        }
    }


    /**
     * Assert that the info can be understood and is safe.
     *
     * @param string $field
     * @param string $operator
     * @param mixed  $value
     * @return void
     * @throws InvalidFilterException
     */
    protected function assert(string $field, string $operator, $value): void
    {
        $name = $operator === '' ? $field : "$field ($operator)";

        if ($field[0] === '$') {
            throw new InvalidFilterException("Invalid filter key '$name': Starting with '$' isn't allowed.");
        }

        if (!array_key_exists($operator, self::OPERATORS)) {
            throw new InvalidFilterException("Invalid filter key '$name': Unknown operator '$operator'.");
        }

        if (is_array($value) || (is_object($value) && !$value instanceof BSON\Type)) {
            $this->assertNoMongoOperator($name, $value);
        }
    }

    /**
     * Assert that the value doesn't contain a MongoDB operator.
     *
     * @param string          $name
     * @param iterable|object $element
     * @param int             $depth
     * @return void
     * @throws InvalidFilterException
     */
    protected function assertNoMongoOperator(string $name, $element, int $depth = 0): void
    {
        if ($depth >= 32) {
            throw new \OverflowException("Unable to apply filter '$name'; possible circular reference");
        }

        foreach ($element as $key => $value) {
            if (is_string($key) && $key[0] === '$') {
                $structure = is_object($element) ? 'object property' : 'array key';
                $message = "Invalid filter value for '$name': "
                    . "Illegal $structure '$key', starting with '$' isn't allowed.";

                throw new InvalidFilterException($message);
            }

            if (is_array($value) || (is_object($value) && !$value instanceof BSON\Type)) {
                $this->assertNoMongoOperator($name, $value, $depth + 1); // recursion
            }
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
    protected function apply(Query $query, string $field, string $operator, $value): void
    {
        $this->assert($field, $operator, $value);

        $mongoOperator = self::OPERATORS[$operator];
        $condition = isset($mongoOperator) ? [$field => [$mongoOperator => $value]] : [$field => $value];

        $query->add($condition);
    }
}
