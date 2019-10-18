<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\QueryBuilder;

use Jasny\DB\Mongo\QueryBuilder\FilterQuery;
use Jasny\DB\Mongo\QueryBuilder\UpdateQueryBuilder;
use Jasny\DB\Option as opt;
use Jasny\DB\QueryBuilder\StagedQueryBuilder;
use Jasny\DB\Update as update;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\QueryBuilder\UpdateQueryBuilder
 */
class UpdateQueryBuilderTest extends TestCase
{
    public function test()
    {
        $builder = new UpdateQueryBuilder();
        $this->assertInstanceOf(StagedQueryBuilder::class, $builder);

        /** @var FilterQuery $query */
        $query = $builder->buildQuery(
            [
                update\set('id', 10),
                update\set('foo', 42),
                update\inc('count'),
                update\patch('bar', ['one' => 1, 'two' => 2])
            ],
            [opt\limit(1)]
        );

        $this->assertInstanceOf(FilterQuery::class, $query);

        $expected = ['$set' => ['_id' => 10, 'foo' => 42, 'bar.one' => 1, 'bar.two' => 2], '$inc' => ['count' => 1]];
        $this->assertEquals($expected, $query->toArray());
        $this->assertEquals(['limit' => 1], $query->getOptions());
    }
}
