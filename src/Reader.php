<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo;

use Jasny\DB\FieldMap\ConfiguredFieldMap;
use Jasny\DB\FieldMap\FieldMapInterface;
use Jasny\DB\Mongo\QueryBuilder\Compose\FilterComposer;
use Jasny\DB\Mongo\QueryBuilder\Finalize\ApplyOptions;
use Jasny\DB\QueryBuilder\FilterQueryBuilder;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\ReadInterface;
use Jasny\DB\Result\ResultBuilder;

/**
 * Fetch data from a MongoDB collection
 */
class Reader implements ReadInterface
{
    use Traits\CollectionTrait;
    use Traits\ResultTrait;
    use Traits\LoggingTrait;
    use Traits\ReadTrait;

    /**
     * Reader constructor.
     */
    public function __construct(QueryBuilderInterface $queryBuilder, ResultBuilder $resultBuilder)
    {
        $this->queryBuilder = $queryBuilder;
        $this->resultBuilder = $resultBuilder;
    }

    /**
     * Get the query builder used by this service.
     */
    public function getQueryBuilder(): QueryBuilderInterface
    {
        return $this->queryBuilder;
    }

    /**
     * Get the result builder used by this service.
     */
    public function getResultBuilder(): ResultBuilder
    {
        return $this->resultBuilder;
    }


    /**
     * Create a reader with the standard query and result builder.
     */
    public static function basic(?FieldMapInterface $map = null): self
    {
        $map ??= new ConfiguredFieldMap([]); // NOP

        $queryBuilder = (new FilterQueryBuilder(new FilterComposer()))
            ->withPreparation([$map, 'applyToFilter'])
            ->withFinalization(new ApplyOptions($map));

        return new static(
            $queryBuilder,
            new ResultBuilder($map),
        );
    }
}
