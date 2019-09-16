<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\QueryBuilder;

use Jasny\DB\Mongo\QueryBuilder\FilterQueryBuilder;
use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\Option as opt;
use Jasny\DB\QueryBuilder\StagedQueryBuilder;
use MongoDB\BSON;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\QueryBuilder\FilterQueryBuilder
 */
class FilterQueryBuilderTest extends TestCase
{
    public function test()
    {
        $builder = new FilterQueryBuilder();
        $this->assertInstanceOf(StagedQueryBuilder::class, $builder);

        /** @var Query $query */
        $query = $builder->buildQuery(
            ['id' => 12, 'status' => 'good', 'info.name(not)' => 'John', 'date(min)' => new \DateTime('2000-01-01')],
            [opt\omit('bio'), opt\limit(1)]
        );

        $this->assertInstanceOf(Query::class, $query);

        $expected = [
            '_id' => 12,
            'status' => 'good',
            'info.name' => ['$ne' => 'John'],
            'date' => ['$gte' => new BSON\UTCDateTime(strtotime('2000-01-01') * 1000)],
        ];
        $this->assertEquals($expected, $query->toArray());
        $this->assertEquals(['projection' => ['bio' => 0], 'limit' => 1], $query->getOptions());
    }
}
