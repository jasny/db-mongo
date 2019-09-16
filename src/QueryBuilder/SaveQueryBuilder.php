<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Improved as i;
use Jasny\DB\FieldMap\ConfiguredFieldMap;
use Jasny\DB\Mongo\QueryBuilder\Step\SaveQueryBuildStep;
use Jasny\DB\Mongo\TypeConversion\CastToMongo;
use Jasny\DB\QueryBuilder\StagedQueryBuilder;

/**
 * Default query builder that builds batches for bulk write.
 * @immutable
 */
class SaveQueryBuilder extends StagedQueryBuilder
{
    public function __construct()
    {
        $configured = $this
            ->onPrepare(fn(iterable $items) => i\iterable_map($items, new ConfiguredFieldMap(['id' => '_id'])))
            ->onPrepare(new CastToMongo())
            ->onCompose(fn(iterable $items) => i\iterable_chunk($items, 100))
            ->onBuild(new SaveQueryBuildStep());

        $this->stages = $configured->stages;
    }
}
