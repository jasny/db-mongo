<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\Read;

use Improved\IteratorPipeline\PipelineBuilder;
use Jasny\DB\Mongo\QueryBuilder\DefaultBuilders;
use Jasny\DB\Mongo\Read\MongoReader;
use Jasny\DB\QueryBuilder\QueryBuilding;
use Jasny\DB\QueryBuilder\StagedQueryBuilder;
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
     * @var QueryBuilding|MockObject
     */
    protected $queryBuilder;

    /**
     * @var PipelineBuilder|MockObject
     */
    protected $resultBuilder;

    public function setUp()
    {
        $this->queryBuilder = $this->createMock(QueryBuilding::class);
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
        /** @var PipelineBuilder|MockObject $builder */
        $builder = $this->createMock(QueryBuilding::class);

        $reader = $this->reader->withResultBuilder($builder);

        $this->assertInstanceOf(MongoReader::class, $reader);
        $this->assertNotSame($this->reader, $reader);

        $this->assertSame($builder, $reader->getResultBuilder());
    }

    public function testWithResultBuilder()
    {
        $builder = new PipelineBuilder();

        $reader = $this->reader->withResultBuilder($builder);

        $this->assertInstanceOf(MongoReader::class, $reader);
        $this->assertNotSame($this->reader, $reader);

        $this->assertSame($builder, $reader->getResultBuilder());
    }


    public function testCount()
    {
        $this->queryBuilder->expects($this->once())->method('__invoke')
            ->with(['foo' => 42, 'color(not)' => 'blue'])
            ->willReturn(['foo' => 42, 'color' => ['$ne' => 'blue']]);

        /** @var Collection|MockObject $collection */
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('count')
            ->with(['foo' => 42, 'color' => ['$ne' => 'blue']])
            ->willReturn(10);

        $count = $this->reader->count($collection, ['foo' => 42, 'color(not)' => 'blue']);

        $this->assertEquals(10, $count);
    }

    public function testFetch()
    {
        $this->markTestIncomplete();
    }
}
