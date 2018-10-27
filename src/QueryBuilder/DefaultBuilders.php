<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Improved as i;
use const Improved\FUNCTION_ARGUMENT_PLACEHOLDER as __;
use Improved\IteratorPipeline\PipelineBuilder;
use Jasny\DB\FieldMap\ConfiguredFieldMap;
use Jasny\DB\Mongo\QueryBuilder\Step\BuildStep;
use Jasny\DB\Mongo\QueryBuilder\Step\FilterComposer;
use Jasny\DB\Mongo\QueryBuilder\Step\SaveQueryBuildStep;
use Jasny\DB\Mongo\QueryBuilder\Step\UpdateComposer;
use Jasny\DB\Mongo\TypeConversion\CastToMongo;
use Jasny\DB\Mongo\TypeConversion\CastToPHP;
use Jasny\DB\QueryBuilder\StagedQueryBuilder;
use Jasny\DB\QueryBuilder\Step\FilterParser;
use Jasny\DB\QueryBuilder\Step\UpdateParser;
use Jasny\DB\Result;

/**
 * Default query and result builders.
 */
final class DefaultBuilders
{
    /**
     * Static class.
     */
    final private function __construct()
    {
    }

    /**
     * Create a builder for `find` and `delete` queries.
     *
     * @return StagedQueryBuilder
     */
    public static function createFilterQueryBuilder(): StagedQueryBuilder
    {
        return (new StagedQueryBuilder)
            ->onPrepare(new FilterParser())
            ->onPrepare(new ConfiguredFieldMap(['id' => '_id', ':id' => '_id']))
            ->onPrepare(new CastToMongo())
            ->onCompose(new FilterComposer())
            ->onBuild(new BuildStep(new OptionConverter()));
    }

    /**
     * Create a builder for `save` queries.
     *
     * @return StagedQueryBuilder
     */
    public static function createSaveQueryBuilder(): StagedQueryBuilder
    {
        return (new StagedQueryBuilder)
            ->onPrepare(i\function_partial(i\iterable_map, __, new ConfiguredFieldMap(['id' => '_id'])))
            ->onPrepare(new CastToMongo())
            ->onCompose(i\function_partial(i\iterable_chunk, __, 100))
            ->onBuild(new SaveQueryBuildStep());
    }

    /**
     * Create a builder for changes in `update` queries.
     *
     * @return StagedQueryBuilder
     */
    public static function createUpdateQueryBuilder(): StagedQueryBuilder
    {
        return (new StagedQueryBuilder)
            ->onPrepare(new UpdateParser())
            ->onPrepare(new ConfiguredFieldMap(['id' => '_id']))
            ->onPrepare(new CastToMongo())
            ->onCompose(new UpdateComposer())
            ->onBuild(new BuildStep(new OptionConverter()));
    }

    /**
     * Create a pipeline builder for a query result.
     *
     * @return PipelineBuilder
     */
    public static function createResultBuilder(): PipelineBuilder
    {
        return (new PipelineBuilder)
            ->then(new ConfiguredFieldMap(['_id' => 'id']))
            ->then(new CastToPHP())
            ->then(function (iterable $iterable) {
                return new Result($iterable);
            });
    }
}
