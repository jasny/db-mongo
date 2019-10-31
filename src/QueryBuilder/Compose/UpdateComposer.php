<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder\Compose;

use Improved as i;
use Jasny\DB\Mongo\Query\UpdateQuery;

/**
 * Standard compose step for update query.
 */
class UpdateComposer
{
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
     * Get the query operation.
     *
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @return array
     */
    protected function getOperation(string $field, string $operator, $value): array
    {
        switch ($operator) {
            case 'set':
                return ['$set' => [$field => $value]];
            case 'patch':
                return ['$set' => flatten_fields($value, $field)];
            case 'inc':
                i\type_check($value, ['int', 'float'], new \UnexpectedValueException());
                return ['$inc' => [$field => $value]];
            case 'mul':
                i\type_check($value, ['int', 'float'], new \UnexpectedValueException());
                return ['$mul' => [$field => $value]];
            case 'div':
                i\type_check($value, ['int', 'float'], new \UnexpectedValueException());
                return ['$mul' => [$field => 1 / (float)$value]];
            case 'push':
                return ['$push' => [$field => ['$each' => $value]]];
            case 'pull':
                return ['$pullAll' => [$field => $value]];

            default:
                throw new \UnexpectedValueException("Unsupported update operator '{$operator}' for '{$field}'");
        }
    }

    /**`
     * Default logic to apply a filter criteria.
     *
     * @param UpdateQuery $query
     * @param string      $field
     * @param string      $operator
     * @param mixed       $value
     * @throws \InvalidArgumentException
     */
    protected function apply(UpdateQuery $query, string $field, string $operator, $value): void
    {
        i\type_check($query, UpdateQuery::class);

        $operation = $this->getOperation($field, $operator, $value);
        $query->add($operation);
    }
}
