<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Improved as i;
use function Jasny\array_without;

/**
 * Stage query builder for `save` queries, build step
 */
class SaveQueryBuildStep
{
    /**
     * Invoke the build step.
     *
     * @param iterable<iterable> $batches
     * @return iterable<array>
     */
    public function __invoke(iterable $batches): iterable
    {
        return i\iterable_map($batches, \Closure::fromCallable([$this, 'createStatements']));
    }

    /**
     * Create query statements
     *
     * @param iterable $batch
     * @return array
     */
    protected function createStatements(iterable $batch): array
    {
        $iterable = i\iterable_map($batch, function($item) {
            $id = $item['_id'] ?? null;
            $values = array_without($item, ['_id']);

            return isset($id)
                ? ['replaceOne' => [['_id' => $id], $values]]
                : ['insertOne' => $values];
        });

        return i\iterable_to_array($iterable);
    }
}
