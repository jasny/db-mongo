<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder\Compose;

use Improved as i;
use Improved\IteratorPipeline\Pipeline;
use Jasny\DB\Mongo\Query\UpdateQuery;
use Jasny\DB\Option\OptionInterface;
use Jasny\DB\Update\UpdateInstruction;
use function Jasny\DB\Mongo\flatten_fields;

/**
 * Standard compose step for update query.
 */
class UpdateComposer
{
    /**`
     * Default logic to apply a filter criteria.
     *
     * @param UpdateQuery       $query
     * @param UpdateInstruction $instruction
     * @param OptionInterface[] $opts
     * @throws \InvalidArgumentException
     */
    public function __invoke(UpdateQuery $query, UpdateInstruction $instruction, array $opts): void
    {
        $operation = $this->getOperation($instruction->getOperator(), $instruction->getPairs());
        $query->add($operation);
    }

    /**
     * Get the query operation.
     *
     * @param string $operator
     * @param array  $value
     * @return array
     */
    protected function getOperation(string $operator, array $pairs): array
    {
        $numPairs = in_array($operator, ['inc', 'mul', 'div'])
            ? Pipeline::with($pairs)->typeCheck(['int', 'float'], new \UnexpectedValueException())
            : null;

        switch ($operator) {
            case 'set':
                return ['$set' => $pairs];
            case 'patch':
                return ['$set' => flatten_fields($pairs)];
            case 'inc':
                return ['$inc' => $numPairs->toArray()];
            case 'mul':
                return ['$mul' => $numPairs->toArray()];
            case 'div':
                return ['$mul' => $numPairs->map(fn($value) => 1 / (float)$value)->toArray()];
            case 'push':
                return ['$push' => Pipeline::with($pairs)->map(fn($value) => ['$each' => $value])->toArray()];
            case 'pull':
                return ['$pullAll' => $pairs];

            default:
                throw new \UnexpectedValueException("Unsupported update operator '{$operator}' for '{$field}'");
        }
    }
}
