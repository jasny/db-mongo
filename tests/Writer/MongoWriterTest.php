<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\Writer;

use Improved\IteratorPipeline\PipelineBuilder;
use Jasny\DB\Mongo\Write\MongoWriter;
use Jasny\DB\QueryBuilding;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\Write\MongoWriter
 */
class MongoWriterTest extends TestCase
{
    /**
     * @var MongoReader
     */
    protected $reader;

    /**
     * @var QueryBuilding|MockObject
     */
    protected $filterQueryBuilder;

    /**
     * @var QueryBuilding|MockObject
     */
    protected $updateQueryBuilder;

    /**
     * @var QueryBuilding|MockObject
     */
    protected $saveQueryBuilder;


    public function setUp()
    {
        $this->queryBuilder = $this->createMock(QueryBuilding::class);

        $this->reader = (new MongoWriter)
            ->withQueryBuilder($this->queryBuilder);
    }


    public function testWithSaveQueryBuilder()
    {
        $this->markTestIncomplete();
    }

    public function testSave()
    {
        $this->markTestIncomplete();
    }

    public function testGetUpdateQueryBuilder()
    {
        $this->markTestIncomplete();
    }

    public function testWithUpdateQueryBuilder()
    {
        $this->markTestIncomplete();
    }

    public function testGetQueryBuilder()
    {
        $this->markTestIncomplete();
    }

    public function testGetSaveQueryBuilder()
    {
        $this->markTestIncomplete();
    }

    public function testUpdate()
    {
        $this->markTestIncomplete();
    }

    public function testWithQueryBuilder()
    {
        $this->markTestIncomplete();
    }

    public function testDelete()
    {
        $this->markTestIncomplete();
    }
}
