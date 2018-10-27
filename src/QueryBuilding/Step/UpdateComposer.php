<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilding\Step;

use Jasny\DB\Exception\InvalidUpdateOperationException;
use Jasny\DB\Mongo\QueryBuilding\Query;
use function Jasny\expect_type;

/**
 * Standard compose step for filter query.
 */
class UpdateComposer extends AbstractComposer
{
    protected const KEY_TYPE = "";

    /**
     * Default operator conversion.
     */
    protected const OPERATORS = [
        'set' => '$set',
        'patch' => '>$set',
        'inc' => '$inc',
        'dec' => '-$inc',
        'mul' => '$mul',
        'div' => '/$mul',
        'push' => '$push',
        'pull' => '$pullAll',
    ];

    /**
     * Create a custom invalid argument exception.
     *
     * @param string $message
     * @return InvalidUpdateOperationException
     */
    protected function invalid(string $message): \InvalidArgumentException
    {
        return new InvalidUpdateOperationException($message);
    }


    /**
     * Flatten all fields of an element.
     *
     * @param mixed  $element
     * @param string $path
     * @param array  $accumulator
     * @return array
     */
    protected function flattenFields($element, string $path, array &$accumulator = []): array
    {
        if (!is_array($element) && !is_object($element)) {
            $accumulator[$path] = $element;
        } else {
            foreach ($element as $key => $value) {
                expect_type($key, 'string', \UnexpectedValueException::class);

                $field = ($path === '' ? $key : "$path.$key");
                $this->flattenFields($value, $field, $accumulator); // recursion
            }
        }

        return $accumulator;
    }

    /**
     * Get the query operation.
     *
     * @param string $field
     * @param string $operator
     * @param $value
     * @return array
     */
    protected function getOperation(string $field, string $operator, $value): array
    {
        $mongoOperator = static::OPERATORS[$operator];

        switch($mongoOperator[0]) {
            case '>':
                return [substr($mongoOperator, 1) => $this->flattenFields($value, $field)];
            case '-':
                expect_type($value, ['int', 'float'], \UnexpectedValueException::class);
                return [substr($mongoOperator, 1) => [$field => -1 * $value]];
            case '/':
                expect_type($value, ['int', 'float'], \UnexpectedValueException::class);
                return [substr($mongoOperator, 1) => [$field => 1 / $value]];
            default:
                return [$mongoOperator => [$field => $value]];
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
     * @throws \InvalidArgumentException
     */
    protected function apply(Query $query, string $field, string $operator, $value): void
    {
        $this->assert($field, $operator, $value);

        $operation = $this->getOperation($field, $operator, $value);
        $query->add($operation);
    }
}
