<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder\Compose;

use Improved as i;
use Improved\IteratorPipeline\Pipeline;
use Jasny\DB\Mongo\Query\WriteQuery;
use Jasny\DB\Option\ConflictResolutionOption;
use Jasny\DB\Option\OptionInterface;

/**
 * Standard compose step for save query.
 */
class SaveComposer
{
    use Traits\FlattenFieldsTrait;

    protected const DEFAULT_RESOLUTION = 'replace';

    private const METHODS = [
        'conflict' => 'insert',
        'ignore' => 'insertIgnore',
        'replace' => 'replace',
        'update' => 'upsert',
    ];

    /**
     * Invoke the composer from the query builder.
     *
     * @param iterable          $items
     * @param OptionInterface[] $opts
     * @return \Generator
     */
    public function __invoke(iterable $items, array $opts): \Generator
    {
        $resolution = Pipeline::with($opts)
                ->filter(fn($opt) => ($opt instanceof ConflictResolutionOption))
                ->map(fn(ConflictResolutionOption $opt) => $opt->getResolution())
                ->last();

        $callback = $this->createCallback($resolution ?? self::DEFAULT_RESOLUTION);
        $exception = new \UnexpectedValueException("Excepted all items to be an array or object; %s given");

        foreach ($items as $index => $item) {
            i\type_check($item, ['array', 'object'], $exception);
            yield ['index' => $index, 'item' => $item] => $callback;
        }
    }

    /**
     * Create the compose callback.
     *
     * @param string $resolution
     * @return \Closure
     */
    protected function createCallback(string $resolution): \Closure
    {
        if (!isset(self::METHODS[$resolution])) {
            throw new \InvalidArgumentException("Unsupported conflict resolution '$resolution'");
        }

        /** @var callable $fn */
        $fn = [$this, self::METHODS[$resolution]];
        return \Closure::fromCallable($fn);
    }


    /**
     * Insert a new item.
     *
     * @param WriteQuery   $query
     * @param array|object $item
     * @param mixed        $index
     */
    public function insert(WriteQuery $query, $item, $index): void
    {
        $query->addIndexed($index, 'insertOne', $item);
    }

    /**
     * Insert a new item if it doesn't exist yet.
     *
     * @param WriteQuery   $query
     * @param array|object $item
     * @param mixed        $index
     */
    public function insertIgnore(WriteQuery $query, $item, $index): void
    {
        $id = $this->getItemId($item);

        $args = $id === null
            ? ['insertOne', $item]
            : ['updateOne', ['_id' => $id], ['$setOnInsert' => $item], ['upsert' => true]];

        $query->addIndexed($index, ...$args);
    }

    /**
     * Insert or replace an item.
     *
     * @param WriteQuery   $query
     * @param array|object $item
     * @param mixed        $index
     */
    public function replace(WriteQuery $query, $item, $index): void
    {
        $id = $this->getItemId($item);

        $args = $id === null
            ? ['insertOne', $item]
            : ['replaceOne', ['_id' => $id], $item, ['upsert' => true]];

        $query->addIndexed($index, ...$args);
    }

    /**
     * Insert an item or update properties if it already exists.
     *
     * @param WriteQuery   $query
     * @param array|object $item
     * @param mixed        $index
     */
    public function upsert(WriteQuery $query, $item, $index): void
    {
        $id = $this->getItemId($item);

        $args = $id === null
            ? ['insertOne', $item]
            : ['updateOne', ['_id' => $id], ['$set' => $this->flattenFields($item)], ['upsert' => true]];

        $query->addIndexed($index, ...$args);
    }

    /**
     * Get the id value of the item or null if there is no id.
     *
     * @param array|object $item
     * @return mixed
     */
    protected function getItemId($item)
    {
        return is_array($item) ? ($item['_id'] ?? null) : ($item->_id ?? null);
    }
}
