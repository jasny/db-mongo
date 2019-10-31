<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder\Finalize;

use Jasny\DB\Mongo\Query\WriteQuery;
use Jasny\DB\Option as opts;
use Jasny\DB\Option\OptionInterface;
use function Jasny\DB\Mongo\flatten_fields;

/**
 * Modify write operations based on conflict resolution setting.
 */
class ConflictResolution
{
    /**
     * Apply conflict resolution.
     *
     * @param WriteQuery        $query
     * @param OptionInterface[] $opts
     */
    public function __invoke(WriteQuery $query, array $opts): void
    {
        $resolution = opts\setting('existing', null)->findIn($opts);

        if ($resolution === null) {
            return;
        }

        $fn = [$this, 'apply' . ucfirst($resolution)];
        if (!is_callable($fn)) {
            throw new \UnexpectedValueException("Unsupported conflict resolution '$resolution'");
        }

        $query->map($fn);
    }

    /**
     * Always insert as a new item.
     *
     * @param array $operation
     * @return array
     */
    public function applyConflict(array $operation): array
    {
        if ($operation[0] === 'insertOne') {
            return $operation;
        }

        return ['insertOne', $operation[2]];
    }

    /**
     * Insert a new item if it doesn't exist yet.
     *
     * @param array $operation
     * @return array
     */
    public function applyIgnore(array $operation): array
    {
        if ($operation[0] === 'insertOne') {
            return $operation;
        }

        [, $filter, $item, $options] = $operation;

        return ['updateOne', $filter, ['$setOnInsert' => $item], ['upsert' => true] + $options];
    }

    /**
     * Insert a new item or replace it if it already exists.
     *
     * @param array $operation
     * @return array
     */
    public function applyReplace(array $operation): array
    {
        if ($operation[0] === 'insertOne') {
            return $operation;
        }

        [, $filter, $item, $options] = $operation;

        return ['replaceOne', $filter, $item, ['upsert' => true] + $options];
    }

    /**
     * Insert an item or update properties if it already exists.
     *
     * @param array $operation
     * @return array
     */
    public function applyUpdate(array $operation): array
    {
        if ($operation[0] === 'insertOne') {
            return $operation;
        }

        [, $filter, $item, $options] = $operation;

        return ['updateOne', $filter, ['$set' => flatten_fields($item)], ['upsert' => true] + $options];
    }
}
