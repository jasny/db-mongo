<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\Writer;

use Jasny\DB\Mongo\Write\MongoWriter;
use Jasny\DB\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\Write\MongoWriter
 */
class MongoWriterTest extends TestCase
{
    /**
     * @var MongoWriter
     */
    protected $writer;

    /**
     * @var QueryBuilder|MockObject
     */
    protected $filterQueryBuilder;

    /**
     * @var QueryBuilder|MockObject
     */
    protected $updateQueryBuilder;

    /**
     * @var QueryBuilder|MockObject
     */
    protected $saveQueryBuilder;


    public function setUp()
    {
        $this->filterQueryBuilder = $this->createMock(QueryBuilder::class);
        $this->updateQueryBuilder = $this->createMock(QueryBuilder::class);
        $this->saveQueryBuilder = $this->createMock(QueryBuilder::class);

        $this->writer = (new MongoWriter)
            ->withQueryBuilder($this->filterQueryBuilder)
            ->withUpdateQueryBuilder($this->updateQueryBuilder)
            ->withSaveQueryBuilder($this->saveQueryBuilder);
    }


    public function testGetQueryBuilder()
    {
        $reader = new MongoReader();
        $builder = $reader->getQueryBuilder();

        $this->assertInstanceOf(StagedQueryBuilder::class, $builder);
        $this->assertEquals(DefaultBuilders::createFilterQueryBuilder(), $builder);
    }

    public function testGetUpdateQueryBuilder()
    {
        $this->markTestIncomplete();
    }

    public function testGetSaveQueryBuilder()
    {
        $this->markTestIncomplete();
    }


    public function testWithQueryBuilder()
    {
        $this->markTestIncomplete();
    }

    public function testWithUpdateQueryBuilder()
    {
        $this->markTestIncomplete();
    }

    public function testWithSaveQueryBuilder()
    {
        $this->markTestIncomplete();
    }


    public function testSave()
    {
        $this->markTestIncomplete();
    }

    public function testUpdate()
    {
        $this->markTestIncomplete();
    }

    public function testDelete()
    {
        $this->markTestIncomplete();
    }
}
