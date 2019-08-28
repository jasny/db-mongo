<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Write\Traits;

use Improved as i;
use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\QueryBuilder;
use Jasny\DB\Update\UpdateOperation;
use Jasny\DB\Result;
use Jasny\DB\Option;
use MongoDB\Collection;
use MongoDB\UpdateResult;
use UnexpectedValueException;

/**
 * Update data of a MongoDB collection.
 */
trait UpdateTrait
{
    /**
     * Get the query builder.
     *
     * @return QueryBuilder
     */
    abstract public function getQueryBuilder(): QueryBuilder;

    /**
     * Get the query builder of updating items.
     *
     * @return QueryBuilder
     */
    abstract public function getUpdateQueryBuilder(): QueryBuilder;

    /**
     * Combine multiple bulk write results into a single result.
     *
     * @param array $ids
     * @param array $meta
     * @return Result&iterable<array>
     */
    abstract protected function createResult(array $ids, array $meta): Result;

    /**
     * Check limit to select 'One' or 'Many' variant of method.
     *
     * @param string   $method
     * @param Option[] $opts
     * @return string
     */
    abstract protected function oneOrMany(string $method, array $opts): string;


    /**
     * Query and update records.
     *
     * @param Collection                        $storage
     * @param array                             $filter
     * @param UpdateOperation|UpdateOperation[] $changes
     * @param Option[]                          $opts
     * @return Result
     */
    public function update($storage, array $filter, $changes, array $opts = []): Result
    {
        /** @var Query $filterQuery */
        $filterQuery = i\type_check(
            $this->getQueryBuilder()->buildQuery($filter, $opts),
            Query::class,
            new UnexpectedValueException()
        );

        /** @var Query $updateQuery */
        $updateQuery = i\type_check(
            $this->getUpdateQueryBuilder()->buildQuery($changes, $opts),
            Query::class,
            new UnexpectedValueException()
        );

        $options = $updateQuery->getOptions() + $filterQuery->getOptions();

        /** @var UpdateResult $updateResult */
        $method = $this->oneOrMany('update', $opts);
        $updateResult = $storage->$method($filterQuery->toArray(), $updateQuery->toArray(), $options);

        $meta = [
            'count' => $updateResult->getModifiedCount() + $updateResult->getUpsertedCount(),
            'matchedCount' => $updateResult->getMatchedCount(),
            'modifiedCount' => $updateResult->getModifiedCount(),
            'upsertedCount' => $updateResult->getUpsertedCount(),
            'acknowledged' => $updateResult->isAcknowledged()
        ];

        $id = $updateResult->getUpsertedId();

        return $this->createResult(isset($id) ? [$id] : [], $meta);
    }
}
