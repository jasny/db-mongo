<?php

namespace Jasny\DB\Mongo\Tests\QueryBuilder;

use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\Option as opt;
use Jasny\DB\Update as update;
use Jasny\DB\Mongo\QueryBuilder\DefaultQueryBuilders;
use Jasny\DB\QueryBuilder\StagedQueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\QueryBuilder\DefaultQueryBuilders
 */
class DefaultQueryBuildersTest extends TestCase
{
    public function testCreateFilterQueryBuilder()
    {
        $builder = DefaultQueryBuilders::createFilterQueryBuilder();
        $this->assertInstanceOf(StagedQueryBuilder::class, $builder);

        /** @var Query $query */
        $query = $builder->buildQuery(
            ['id' => 12, 'status' => 'good', 'info.name (not)' => 'John'],
            [opt\omit('bio'), opt\limit(1)]
        );

        $this->assertInstanceOf(Query::class, $query);
        $this->assertEquals(['_id' => 12, 'status' => 'good', 'info.name' => ['$ne' => 'John']], $query->toArray());
        $this->assertEquals(['projection' => ['bio' => -1], 'limit' => 1], $query->getOptions());
    }

    public function testCreateSaveQueryBuilder()
    {
        $this->markTestIncomplete();
    }

    public function testCreateUpdateQueryBuilder()
    {
        $builder = DefaultQueryBuilders::createUpdateQueryBuilder();
        $this->assertInstanceOf(StagedQueryBuilder::class, $builder);

        /** @var Query $query */
        $query = $builder->buildQuery(
            [
                update\set('foo', 42),
                update\inc('count'),
                update\patch('bar', ['one' => 1, 'two' => 2])
            ],
            [opt\limit(1)]
        );

        $this->assertInstanceOf(Query::class, $query);

        $expected = ['$set' => ['foo' => 42, 'bar.one' => 1, 'bar.two' => 2], '$inc' => ['count' => 1]];
        $this->assertEquals($expected, $query->toArray());
        $this->assertEquals(['limit' => 1], $query->getOptions());
    }
}
