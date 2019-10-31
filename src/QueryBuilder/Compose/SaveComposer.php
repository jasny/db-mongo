<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder\Compose;

use Jasny\DB\Mongo\Query\WriteQuery;
use Jasny\DB\Option\OptionInterface;
use function Jasny\DB\Mongo\flatten_fields;

/**
 * Composers for MongoDB save query builder.
 */
class SaveComposer
{
    /**
     * Insert or replace an item.
     *
     * @param WriteQuery        $query
     * @param array|object      $item
     * @param mixed             $index
     * @param OptionInterface[] $opts
     */
    public function __invoke(WriteQuery $query, $item, $index, array $opts): void
    {
        $id = is_array($item) ? ($item['_id'] ?? null) : ($item->_id ?? null);

        $args = $id === null
            ? ['insertOne', $item]
            : ['replaceOne', ['_id' => $id], $item, ['upsert' => true]];

        $query->addIndexed($index, ...$args);
    }
}
