<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\QueryBuilder;

use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\Option as opt;
use Jasny\DB\Update as update;
use Jasny\DB\Mongo\QueryBuilder\DefaultBuilders;
use Jasny\DB\QueryBuilder\StagedQueryBuilder;
use MongoDB\BSON;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\QueryBuilder\DefaultBuilders
 */
class DefaultBuildersTest extends TestCase
{
    public function testCreateFilterQueryBuilder()
    {
        $builder = DefaultBuilders::createFilterQueryBuilder();
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
        $this->assertEquals(['projection' => ['bio' => -1], 'limit' => 1], $query->getOptions());
    }

    public function testCreateSaveQueryBuilder()
    {
        $builder = DefaultBuilders::createSaveQueryBuilder();
        $this->assertInstanceOf(StagedQueryBuilder::class, $builder);

        $batchesIterator = $builder->buildQuery([
            ['id' => 10, 'foo' => 42, 'color' => 'red'],
            ['id' => 12, 'foo' => 99, 'color' => 'green'],
            ['foo' => 3, 'color' => 'blue'],
        ]);
        $this->assertInstanceOf(\Iterator::class, $batchesIterator);

        $batches = iterator_to_array($batchesIterator, true);
        $this->assertCount(1, $batches);

        $expected = [
            ['replaceOne' => [['_id' => 10], ['foo' => 42, 'color' => 'red']]],
            ['replaceOne' => [['_id' => 12], ['foo' => 99, 'color' => 'green']]],
            ['insertOne' => ['foo' => 3, 'color' => 'blue']],
        ];
        $this->assertEquals($expected, $batches[0]);
    }

    public function testCreateUpdateQueryBuilder()
    {
        $builder = DefaultBuilders::createUpdateQueryBuilder();
        $this->assertInstanceOf(StagedQueryBuilder::class, $builder);

        /** @var Query $query */
        $query = $builder->buildQuery(
            [
                update\set('id', 10),
                update\set('foo', 42),
                update\inc('count'),
                update\patch('bar', ['one' => 1, 'two' => 2])
            ],
            [opt\limit(1)]
        );

        $this->assertInstanceOf(Query::class, $query);

        $expected = ['$set' => ['_id' => 10, 'foo' => 42, 'bar.one' => 1, 'bar.two' => 2], '$inc' => ['count' => 1]];
        $this->assertEquals($expected, $query->toArray());
        $this->assertEquals(['limit' => 1], $query->getOptions());
    }
}
