<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\Result;

use Jasny\DB\Mongo\Result\ResultBuilder;
use Jasny\DB\Result;
use MongoDB\BSON;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\Result\ResultBuilder
 */
class ResultBuilderTest extends TestCase
{
    public function documentProvider()
    {
        $documents = [
            ['foo' => 'bar'],
            ['_id' => 1, 'foo' => 'bar'],
            ['_id' => new BSON\ObjectId('5a2493c33c95a1281836eb6a')],
            ['date' => new BSON\UTCDateTime(strtotime('2000-01-01') * 1000)],
        ];

        $expected = [
            ['foo' => 'bar'],
            ['id' => 1, 'foo' => 'bar'],
            ['id' => '5a2493c33c95a1281836eb6a'],
            ['date' => new \DateTimeImmutable('2000-01-01')],
        ];

        return [
            'array'         => [$documents, $expected],
            'ArrayIterator' => [new \ArrayIterator($documents), $expected],
            'ArrayObject'   => [new \ArrayObject($documents), $expected],
            'SplFixedArray' => [\SplFixedArray::fromArray(array_values($documents)), $expected]
        ];
    }

    /**
     * @dataProvider documentProvider
     */
    public function test(iterable $documents, $expected)
    {
        $builder = new ResultBuilder();

        $result = $builder->with($documents);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals($expected, $result->toArray());
    }
}
