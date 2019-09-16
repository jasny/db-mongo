<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder\Step;

use Improved as i;
use Jasny\DB\Exception\InvalidUpdateOperationException;
use Jasny\DB\Mongo\QueryBuilder\Query;

/**
 * Standard compose step for filter query.
 */
class UpdateComposer extends AbstractComposer
{
    /**
     * Default operator conversion.
     */
    protected const OPERATORS = [
        'set'   => '$set',
        'patch' => '>$set',
        'inc'   => '$inc',
        'mul'   => '$mul',
        'div'   => '/$mul',
        'push'  => '$push $each',
        'pull'  => '$pullAll',
    ];

    /**
     * Create a custom invalid argument exception.
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
                i\type_check($key, 'string', new \UnexpectedValueException());

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
        [$mongoOperator, $modifier] = explode(' ', static::OPERATORS[$operator]) + [1 => null];

        if ($modifier !== null) {
            $value = [$modifier => $value];
        }

        switch ($mongoOperator[0]) {
            case '>':
                return [substr($mongoOperator, 1) => $this->flattenFields($value, $field)];
            case '/':
                i\type_check($value, ['int', 'float'], new \UnexpectedValueException());
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
