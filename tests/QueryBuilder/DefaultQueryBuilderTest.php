<?php

namespace Jasny\DB\Mongo\Tests\QueryBuilder;

use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\Option as opt;
use Jasny\DB\Mongo\QueryBuilder\DefaultQueryBuilder;
use Jasny\DB\QueryBuilder\StagedQueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\QueryBuilder\DefaultQueryBuilder
 */
class DefaultQueryBuilderTest extends TestCase
{
    public function testCreateFilterQueryBuilder()
    {
        $builder = DefaultQueryBuilder::createFilterQueryBuilder();
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
        $this->markTestIncomplete();
    }
}
