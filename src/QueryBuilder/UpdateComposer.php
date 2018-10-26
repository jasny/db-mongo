<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use MongoDB\BSON;
use Jasny\DB\Exception\InvalidFilterException;
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
     * Flatten all fields of an element.
     *
     * @param iterable|object $element
     * @param string          $path
     * @return array
     */
    protected function flattenFields($element, $path = ''): array
    {
        $pairs = [];

        foreach ($element as $key => $value) {
            $field = $path === '' ? $key : "$path.$key";

            $pairs[] = is_array($value) || (is_object($value) && !$value instanceof BSON\Type)
                ? $this->flattenFields($value, $field)
                : [$field => $value];
        }

        return array_merge(...$pairs);
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
                return [substr($mongoOperator, 1) => $this->flattenFields([$field => $value])];
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
     * @throws InvalidFilterException
     */
    protected function apply(Query $query, string $field, string $operator, $value): void
    {
        $this->assert($field, $operator, $value);

        $operation = $this->getOperation($field, $operator, $value);
        $query->add($operation);
    }
}
