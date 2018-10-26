<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Improved as i;
use const Improved\FUNCTION_ARGUMENT_PLACEHOLDER as ___;
use Jasny\DB\FieldMap\FieldMap;
use Jasny\DB\Mongo\TypeConversion\CastToMongo;
use Jasny\DB\QueryBuilder\FilterParser;
use Jasny\DB\QueryBuilder\StagedQueryBuilder;
use Jasny\DB\Update\UpdateParser;

/**
 * Default query builders
 */
final class DefaultQueryBuilders
{
    /**
     * Create a builder for `find` and `delete` queries.
     *
     * @return StagedQueryBuilder
     */
    public static function createFilterQueryBuilder(): StagedQueryBuilder
    {
        return (new StagedQueryBuilder)
            ->onPrepare(new FilterParser())
            ->onPrepare(new FieldMap(['id' => '_id', ':id' => '_id']))
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
            ->onPrepare(i\function_partial(i\iterable_map, ___, new FieldMap(['id' => '_id'])))
            ->onPrepare(new CastToMongo())
            ->onCompose(i\function_partial(i\iterable_chunk, ___, 100))
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
            ->onPrepare(new FieldMap(['id' => '_id']))
            ->onPrepare(new CastToMongo())
            ->onCompose(new UpdateComposer())
            ->onBuild(new BuildStep(new OptionConverter()));
    }
}
