<?php declare(strict_types=1);

namespace Jasny\DB\Mongo;

use Jasny\DB\FieldMap\ConfiguredFieldMap;
use Jasny\DB\FieldMap\FieldMapInterface;
use Jasny\DB\Mongo\QueryBuilder\Compose\FilterComposer;
use Jasny\DB\Mongo\QueryBuilder\Compose\SaveComposer;
use Jasny\DB\Mongo\QueryBuilder\Compose\UpdateComposer;
use Jasny\DB\Mongo\QueryBuilder\Finalize\ApplyOptions;
use Jasny\DB\Mongo\QueryBuilder\Finalize\ConflictResolution;
use Jasny\DB\Mongo\QueryBuilder\Finalize\OneOrMany;
use Jasny\DB\QueryBuilder\FilterQueryBuilder;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\QueryBuilder\SaveQueryBuilder;
use Jasny\DB\QueryBuilder\UpdateQueryBuilder;
use Jasny\DB\Result\ResultBuilder;
use Jasny\DB\WriteInterface;

/**
 * Fetch data from a MongoDB collection
 */
class Writer implements WriteInterface
{
    use Traits\CollectionTrait;
    use Traits\ResultTrait;
    use Traits\LoggingTrait;
    use Traits\SaveTrait;
    use Traits\UpdateTrait;
    use Traits\DeleteTrait;

    /**
     * Reader constructor.
     */
    public function __construct(
        QueryBuilderInterface $queryBuilder,
        QueryBuilderInterface $updateQueryBuilder,
        QueryBuilderInterface $saveQueryBuilder,
        ResultBuilder $resultBuilder
    ) {
        $this->queryBuilder = $queryBuilder;
        $this->updateQueryBuilder = $updateQueryBuilder;
        $this->saveQueryBuilder = $saveQueryBuilder;
        $this->resultBuilder = $resultBuilder;
    }

    /**
     * Get the query builder used by this service.
     * This builder is used for filtering delete and update queries.
     */
    public function getQueryBuilder(): QueryBuilderInterface
    {
        return $this->queryBuilder;
    }

    /**
     * Get the update query builder used by this service.
     */
    public function getUpdateQueryBuilder(): QueryBuilderInterface
    {
        return $this->updateQueryBuilder;
    }

    /**
     * Get the update query builder used by this service.
     */
    public function getSaveQueryBuilder(): QueryBuilderInterface
    {
        return $this->saveQueryBuilder;
    }

    /**
     * Get the result builder used by this service.
     */
    public function getResultBuilder(): ResultBuilder
    {
        return $this->resultBuilder;
    }


    /**
     * Create a writer with standard query and result builder.
     */
    public static function basic(?FieldMapInterface $map = null): self
    {
        $map ??= new ConfiguredFieldMap([]); // NULL object

        $filterQueryBuilder = (new FilterQueryBuilder(new FilterComposer()))
            ->withPreparation([$map, 'applyToFilter'])
            ->withFinalization(new OneOrMany());

        $updateQueryBuilder = (new UpdateQueryBuilder(new UpdateComposer()))
            ->withPreparation([$map, 'applyToUpdate']);

        $saveQueryBuilder = (new SaveQueryBuilder(new SaveComposer()))
            ->withPreparation([$map, 'applyToItems'])
            ->withFinalization(new ConflictResolution());

        return new static(
            $filterQueryBuilder,
            $updateQueryBuilder,
            $saveQueryBuilder,
            new ResultBuilder($map)
        );
    }
}
