<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Jasny\DB\FieldMap\ConfiguredFieldMap;
use Jasny\DB\Mongo\QueryBuilder\Step\BuildStep;
use Jasny\DB\Mongo\QueryBuilder\Step\UpdateComposer;
use Jasny\DB\Mongo\TypeConversion\CastToMongo;
use Jasny\DB\QueryBuilder\StagedQueryBuilder;
use Jasny\DB\QueryBuilder\Step\UpdateParser;

/**
 * Default query builder for the update parameter of the update method.
 * @immutable
 */
class UpdateQueryBuilder extends StagedQueryBuilder
{
    public function __construct()
    {
        $configured = $this
            ->onPrepare(new UpdateParser())
            ->onPrepare(new ConfiguredFieldMap(['id' => '_id']))
            ->onPrepare(new CastToMongo())
            ->onCompose(new UpdateComposer())
            ->onBuild(new BuildStep(new OptionConverter()));

        $this->stages = $configured->stages;
    }
}
