<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Jasny\DB\FieldMap\ConfiguredFieldMap;
use Jasny\DB\Mongo\QueryBuilder\Step\BuildStep;
use Jasny\DB\Mongo\QueryBuilder\Step\FilterComposer;
use Jasny\DB\Mongo\TypeConversion\CastToMongo;
use Jasny\DB\QueryBuilder\StagedQueryBuilder;
use Jasny\DB\QueryBuilder\Step\FilterParser;

/**
 * Default query builder for the query selectors of an any time of collection method.
 * @immutable
 */
class FilterQueryBuilder extends StagedQueryBuilder
{
    public function __construct()
    {
        $configured = $this
            ->onPrepare(new FilterParser())
            ->onPrepare(new ConfiguredFieldMap(['id' => '_id']))
            ->onPrepare(new CastToMongo())
            ->onCompose(new FilterComposer())
            ->onBuild(new BuildStep(new OptionConverter()));

        $this->stages = $configured->stages;
    }
}
