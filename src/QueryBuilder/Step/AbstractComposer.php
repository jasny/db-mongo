<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder\Step;

use Improved as i;
use InvalidArgumentException;
use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\Exception\InvalidFilterException;
use MongoDB\BSON;
use OverflowException;
use UnexpectedValueException;

/**
 * Base class for compose step.
 * @immutable
 */
abstract class AbstractComposer
{
    /**
     * Default operator conversion
     */
    protected const OPERATORS = [];

    /**
     * Invoke the composer.
     */
    public function __invoke(iterable $iterable): \Generator
    {
        $callback = \Closure::fromCallable([$this, 'apply']);
        $exception = new UnexpectedValueException("Excepted keys to be an array; %s given");

        foreach ($iterable as $info => $value) {
            i\type_check($info, 'array', $exception);
            $info['value'] = $value;

            yield $info => $callback;
        }
    }


    /**
     * Create a custom invalid argument exception.
     *
     * @param string $message
     * @return InvalidArgumentException
     */
    abstract protected function invalid(string $message): InvalidArgumentException;


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

        if ($field[0] === '$' || strpos($field, '.$') !== false) {
            throw $this->invalid("Invalid field '$name': Starting with '$' isn't allowed");
        }

        if (!array_key_exists($operator, static::OPERATORS)) {
            throw $this->invalid("Invalid field '$name': Unknown operator '$operator'");
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
            throw new OverflowException("Unable to apply '$name'; possible circular reference");
        }

        foreach ($element as $key => $value) {
            if (is_string($key) && ($key[0] === '$' || strpos($key, '.$') !== false)) {
                $err = "Invalid filter value for '%s': Illegal %s '%s', starting with '$' isn't allowed";
                $keyDesc = (is_object($element) ? 'object property' : 'array key');
                throw $this->invalid(sprintf($err, $name, $keyDesc, $key));
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
    abstract protected function apply(Query $query, string $field, string $operator, $value): void;
}
