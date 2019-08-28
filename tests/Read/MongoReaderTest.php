<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\Read;

use Improved\IteratorPipeline\PipelineBuilder;
use Jasny\DB\Mongo\QueryBuilder\DefaultBuilders;
use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\Mongo\Read\MongoReader;
use Jasny\DB\Option as opt;
use Jasny\DB\QueryBuilder;
use Jasny\DB\QueryBuilder\StagedQueryBuilder;
use Jasny\DB\Result;
use MongoDB\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\Read\MongoReader
 */
class MongoReaderTest extends TestCase
{
    /**
     * @var MongoReader
     */
    protected $reader;

    /**
     * @var QueryBuilder|MockObject
     */
    protected $queryBuilder;

    /**
     * @var PipelineBuilder|MockObject
     */
    protected $resultBuilder;


    public function setUp(): void
    {
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->resultBuilder = $this->createMock(PipelineBuilder::class);

        $this->reader = (new MongoReader)
            ->withQueryBuilder($this->queryBuilder)
            ->withResultBuilder($this->resultBuilder);
    }


    public function testGetQueryBuilder()
    {
        $reader = new MongoReader();
        $builder = $reader->getQueryBuilder();

        $this->assertInstanceOf(StagedQueryBuilder::class, $builder);
        $this->assertEquals(DefaultBuilders::createFilterQueryBuilder(), $builder);
    }

    public function testGetResultBuilder()
    {
        $reader = new MongoReader();
        $builder = $reader->getResultBuilder();

        $this->assertInstanceOf(PipelineBuilder::class, $builder);
        $this->assertEquals(DefaultBuilders::createResultBuilder(), $builder);
    }

    public function testWithQueryBuilder()
    {
        /** @var QueryBuilder|MockObject $builder */
        $builder = $this->createMock(QueryBuilder::class);

        $reader = $this->reader->withQueryBuilder($builder);

        $this->assertInstanceOf(MongoReader::class, $reader);
        $this->assertNotSame($this->reader, $reader);

        $this->assertSame($builder, $reader->getQueryBuilder());

        $this->assertSame($reader, $reader->withQueryBuilder($builder), 'Idempotent');
    }

    public function testWithResultBuilder()
    {
        $builder = new PipelineBuilder();

        $reader = $this->reader->withResultBuilder($builder);

        $this->assertInstanceOf(MongoReader::class, $reader);
        $this->assertNotSame($this->reader, $reader);

        $this->assertSame($builder, $reader->getResultBuilder());

        $this->assertSame($reader, $reader->withResultBuilder($builder), 'Idempotent');
    }


    public function testCount()
    {
        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('toArray')
            ->willReturn(['foo' => 42, 'color' => ['$ne' => 'blue']]);
        $query->expects($this->once())->method('getOptions')
            ->willReturn(['limit' => 10]);

        $this->queryBuilder->expects($this->once())->method('buildQuery')
            ->with(['foo' => 42, 'color(not)' => 'blue'], [opt\limit(10)])
            ->willReturn($query);

        /** @var Collection|MockObject $collection */
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('countDocuments')
            ->with(['foo' => 42, 'color' => ['$ne' => 'blue']])
            ->willReturn(10);

        $count = $this->reader->count($collection, ['foo' => 42, 'color(not)' => 'blue'], [opt\limit(10)]);

        $this->assertEquals(10, $count);
    }

    public function testFetch()
    {
        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('toArray')
            ->willReturn(['foo' => 42, 'color' => ['$ne' => 'blue']]);
        $query->expects($this->once())->method('getOptions')
            ->willReturn(['limit' => 10]);

        $this->queryBuilder->expects($this->once())->method('buildQuery')
            ->with(['foo' => 42, 'color(not)' => 'blue'], [opt\limit(10)])
            ->willReturn($query);

        $cursor = new \ArrayIterator([
            ['foo' => 42, 'color' => 'red'],
            ['foo' => 42, 'color' => 'blue']
        ]);

        /** @var Collection|MockObject $collection */
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('find')
            ->with(['foo' => 42, 'color' => ['$ne' => 'blue']])
            ->willReturn($cursor);

        $expected = new Result();

        $this->resultBuilder->expects($this->once())->method('with')
            ->with($cursor)
            ->willReturn($expected);

        $result = $this->reader->fetch($collection, ['foo' => 42, 'color(not)' => 'blue'], [opt\limit(10)]);

        $this->assertSame($expected, $result);
    }
}
