<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Write\Traits;

use Improved as i;
use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\Mongo\QueryBuilder\UpdateQueryBuilder;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\Update\UpdateOperation;
use Jasny\DB\Result;
use Jasny\DB\Option\OptionInterface;
use Jasny\DB\Write\WriteInterface;
use MongoDB\Collection;
use MongoDB\UpdateResult;
use UnexpectedValueException;

/**
 * Update data of a MongoDB collection.
 */
trait UpdateTrait
{
    /**
     * Get MongoDB collection object.
     */
    abstract public function getStorage(): Collection;

    /**
     * Get the query builder.
     *
     * @return QueryBuilderInterface
     */
    abstract public function getQueryBuilder(): QueryBuilderInterface;

    /**
     * Check limit to select 'One' or 'Many' variant of method.
     *
     * @param string            $method
     * @param OptionInterface[] $opts
     * @return string
     */
    abstract protected function oneOrMany(string $method, array $opts): string;


    /**
     * Get the query builder of updating items.
     *
     * @return QueryBuilderInterface
     */
    public function getUpdateQueryBuilder(): QueryBuilderInterface
    {
        $this->updateQueryBuilder ??= new UpdateQueryBuilder();

        return $this->updateQueryBuilder;
    }

    /**
     * Create a reader with a custom query builder.
     *
     * @param QueryBuilderInterface $builder
     * @return static
     */
    public function withUpdateQueryBuilder(QueryBuilderInterface $builder): WriteInterface
    {
        return $this->with('updateQueryBuilder', $builder);
    }

    /**
     * Query and update records.
     *
     * @param array             $filter
     * @param UpdateOperation[] $changes
     * @param OptionInterface[] $opts
     * @return Result
     */
    public function update(array $filter, array $changes, array $opts = []): Result
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
        $update = $this->oneOrMany('update', $opts);
        $updateResult = $update($filterQuery->toArray(), $updateQuery->toArray(), $options);

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
