<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Write\Traits;

use Improved as i;
use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\Result;
use Jasny\DB\Option\OptionInterface;
use MongoDB\Collection;
use MongoDB\DeleteResult;

/**
 * Delete data from a MongoDB collection.
 */
trait DeleteTrait
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
     * @param string            $method
     * @param OptionInterface[] $opts
     * @return string
     */
    abstract protected function oneOrMany(string $method, array $opts): string;


    /**
     * Query and delete records.
     *
     * @param array             $filter
     * @param OptionInterface[] $opts
     * @return Result
     */
    public function delete(array $filter, array $opts = []): Result
    {
        /** @var Query $query */
        $query = i\type_check(
            $this->getQueryBuilder()->buildQuery($filter, $opts),
            Query::class,
            new \UnexpectedValueException()
        );

        $delete = $this->oneOrMany('delete', $opts);

        /** @var DeleteResult $deleteResult */
        $deleteResult = $delete($query->toArray(), $query->getOptions());

        $meta = [
            'count' => $deleteResult->getDeletedCount(),
            'deletedCount' => $deleteResult->getDeletedCount(),
            'acknowledged' => $deleteResult->isAcknowledged()
        ];

        return $this->createResult([], $meta);
    }
}
