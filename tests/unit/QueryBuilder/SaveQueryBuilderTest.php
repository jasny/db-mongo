<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\QueryBuilder;

use Jasny\DB\Mongo\QueryBuilder\SaveQueryBuilder;
use Jasny\DB\QueryBuilder\StagedQueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\QueryBuilder\SaveQueryBuilder
 */
class SaveQueryBuilderTest extends TestCase
{
    public function test()
    {
        $builder = new SaveQueryBuilder();
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
}
