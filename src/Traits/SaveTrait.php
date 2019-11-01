<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Traits;

use Improved as i;
use Jasny\DB\Mongo\Query\WriteQuery;
use Jasny\DB\Option as opts;
use Jasny\DB\Option\OptionInterface;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\Result\Result;
use MongoDB\BulkWriteResult;
use MongoDB\Collection;
use UnexpectedValueException;
use function Jasny\object_set_properties;

/**
 * Save data to a MongoDB collection.
 */
trait SaveTrait
{
    protected QueryBuilderInterface $saveQueryBuilder;

    /**
     * Get MongoDB collection object.
     */
    abstract public function getCollection(): Collection;

    /**
     * Create a result.
     */
    abstract protected function createResult(iterable $cursor, array $meta = []): Result;


    /**
     * Save the one item.
     * Returns a result with the generated id.
     *
     * @param object|array $item
     * @param OptionInterface[] $opts
     * @return Result
     * @throws BuildQueryException
     */
    public function save($item, array $opts = []): Result
    {
        return $this->saveAll([$item], $opts);
    }

    /**
     * Save multiple items.
     * Returns a result with the generated ids.
     *
     * @param iterable          $items
     * @param OptionInterface[] $opts
     * @return Result
     */
    public function saveAll(iterable $items, array $opts = []): Result
    {
        $query = new WriteQuery(['ordered' => false]);
        $this->saveQueryBuilder->apply($query, $items, $opts);

        $query->expectMethods('insertOne', 'replaceOne', 'updateOne');
        $mongoOperations = $query->getOperations();
        $mongoOptions = $query->getOptions();

        $this->debug("%s.bulkWrite", ['operations' => $mongoOperations, 'options' => $mongoOptions]);

        $writeResult = $this->getCollection()->bulkWrite($mongoOperations, $mongoOptions);

        $result = $this->createSaveResult($query->getIndex(), $writeResult);

        if (opts\apply_result()->isIn($opts)) {
            /** @var array $items */
            $result = $result->applyTo($items);
        }

        return $result;
    }

    /**
     * Aggregate the meta from multiple bulk write actions.
     *
     * @param array             $index
     * @param BulkWriteResult   $writeResult
     * @return Result
     */
    protected function createSaveResult(array $index, BulkWriteResult $writeResult): Result
    {
        $meta = [];

        if ($writeResult->isAcknowledged()) {
            $meta['count'] = $writeResult->getInsertedCount()
                + (int)$writeResult->getModifiedCount()
                + $writeResult->getUpsertedCount();
            $meta['matched'] = $writeResult->getMatchedCount();
            $meta['inserted'] = $writeResult->getInsertedCount();
            $meta['modified'] = $writeResult->getModifiedCount();
        }

        $ids = $writeResult->getInsertedIds()
            + $writeResult->getUpsertedIds()
            + array_fill(0, count($index), null);

        // Turn id values into arrays before mapping is applied.
        $documents = i\iterable_map($ids, fn($id) => ($id === null ? [] : ['_id' => $id]));

        return $this->createResult($documents, $meta)->setKeys($index);
    }
}
