<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\Writer;

use Improved as i;
use Improved\IteratorPipeline\PipelineBuilder;
use Jasny\DB\Exception\InvalidOptionException;
use Jasny\DB\Mongo\QueryBuilder\FilterQuery;
use Jasny\DB\Mongo\QueryBuilder\FilterQueryBuilder;
use Jasny\DB\Mongo\QueryBuilder\SaveQueryBuilder;
use Jasny\DB\Mongo\QueryBuilder\UpdateQueryBuilder;
use Jasny\DB\Mongo\Result\ResultBuilder;
use Jasny\DB\Mongo\Traits\Writer;
use Jasny\DB\Option as opt;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\Result;
use Jasny\DB\Update as update;
use MongoDB\BulkWriteResult;
use MongoDB\Collection;
use MongoDB\DeleteResult;
use MongoDB\UpdateResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\Traits\Writer
 * @covers \Jasny\DB\Mongo\Traits\Traits\SaveTrait
 * @covers \Jasny\DB\Mongo\Traits\Traits\UpdateTrait
 * @covers \Jasny\DB\Mongo\Traits\Traits\DeleteTrait
 */
class MongoWriterTest extends TestCase
{
    /**
     * @var Writer
     */
    protected $writer;

    /**
     * @var Collection|MockObject
     */
    protected $collection;

    /**
     * @var QueryBuilderInterface|MockObject
     */
    protected $filterQueryBuilder;

    /**
     * @var QueryBuilderInterface|MockObject
     */
    protected $updateQueryBuilder;

    /**
     * @var QueryBuilderInterface|MockObject
     */
    protected $saveQueryBuilder;

    /**
     * @var PipelineBuilder|MockObject
     */
    protected $resultBuilder;


    public function setUp(): void
    {
        $this->collection = $this->createMock(Collection::class);

        $this->filterQueryBuilder = $this->createMock(QueryBuilderInterface::class);
        $this->updateQueryBuilder = $this->createMock(QueryBuilderInterface::class);
        $this->saveQueryBuilder = $this->createMock(QueryBuilderInterface::class);
        $this->resultBuilder = $this->createMock(PipelineBuilder::class);

        $this->writer = (new Writer($this->collection))
            ->withQueryBuilder($this->filterQueryBuilder)
            ->withUpdateQueryBuilder($this->updateQueryBuilder)
            ->withSaveQueryBuilder($this->saveQueryBuilder)
            ->withResultBuilder($this->resultBuilder);
    }


    public function testGetQueryBuilder()
    {
        $writer = new Writer($this->collection);
        $builder = $writer->getQueryBuilder();

        $this->assertInstanceOf(FilterQueryBuilder::class, $builder);
    }

    public function testGetUpdateQueryBuilder()
    {
        $writer = new Writer($this->collection);
        $builder = $writer->getUpdateQueryBuilder();

        $this->assertInstanceOf(UpdateQueryBuilder::class, $builder);
    }

    public function testGetSaveQueryBuilder()
    {
        $writer = new Writer($this->collection);
        $builder = $writer->getSaveQueryBuilder();

        $this->assertInstanceOf(SaveQueryBuilder::class, $builder);
    }

    public function testGetResultBuilder()
    {
        $writer = new Writer($this->collection);
        $builder = $writer->getResultBuilder();

        $this->assertInstanceOf(ResultBuilder::class, $builder);
    }


    public function testWithQueryBuilder()
    {
        /** @var QueryBuilderInterface|MockObject $builder */
        $builder = $this->createMock(QueryBuilderInterface::class);

        $writer = $this->writer->withQueryBuilder($builder);

        $this->assertInstanceOf(Writer::class, $writer);
        $this->assertNotSame($this->writer, $writer);

        $this->assertSame($builder, $writer->getQueryBuilder());
        $this->assertSame($writer, $writer->withQueryBuilder($builder), 'Idempotent');
    }

    public function testWithUpdateQueryBuilder()
    {
        /** @var QueryBuilderInterface|MockObject $builder */
        $builder = $this->createMock(QueryBuilderInterface::class);

        $writer = $this->writer->withUpdateQueryBuilder($builder);

        $this->assertInstanceOf(Writer::class, $writer);
        $this->assertNotSame($this->writer, $writer);

        $this->assertSame($builder, $writer->getUpdateQueryBuilder());
        $this->assertSame($writer, $writer->withUpdateQueryBuilder($builder), 'Idempotent');
    }

    public function testWithSaveQueryBuilder()
    {
        /** @var QueryBuilderInterface|MockObject $builder */
        $builder = $this->createMock(QueryBuilderInterface::class);

        $writer = $this->writer->withSaveQueryBuilder($builder);

        $this->assertInstanceOf(Writer::class, $writer);
        $this->assertNotSame($this->writer, $writer);

        $this->assertSame($builder, $writer->getSaveQueryBuilder());
        $this->assertSame($writer, $writer->withSaveQueryBuilder($builder), 'Idempotent');
    }

    public function testWithResultBuilder()
    {
        $builder = new PipelineBuilder();

        $writer = $this->writer->withResultBuilder($builder);

        $this->assertInstanceOf(Writer::class, $writer);
        $this->assertNotSame($this->writer, $writer);

        $this->assertSame($builder, $writer->getResultBuilder());
        $this->assertSame($writer, $writer->withResultBuilder($builder), 'Idempotent');
    }

    
    public function documentsProvider()
    {
        $documents = [
            ['id' => 10, 'foo' => 42, 'color' => 'red'],
            ['id' => 12, 'foo' => 99, 'color' => 'green'],
            ['id' => 13, 'foo' => 100, 'color' => 'green'],
            ['id' => null, 'foo' => 3, 'color' => 'blue'],
            ['id' => 17, 'color' => 'red'],
            ['foo' => 4]
        ];

        $batches = [
            [
                ['replaceOne' => [['_id' => 10], ['foo' => 42, 'color' => 'red']]],
                ['replaceOne' => [['_id' => 12], ['foo' => 99, 'color' => 'green']]],
                ['replaceOne' => [['_id' => 13], ['foo' => 100, 'color' => 'green']]],
                ['insertOne' => ['foo' => 3, 'color' => 'blue']]
            ],
            [
                ['replaceOne' => [['_id' => 17], ['color' => 'red']]],
                ['insertOne' => ['foo' => 4]]
            ]
        ];

        return [
            'batches' => [$documents, $batches]
        ];
    }

    /**
     * @dataProvider documentsProvider
     */
    public function testSave(array $documents, array $batches)
    {
        $this->saveQueryBuilder->expects($this->once())->method('buildQuery')
            ->with($documents, [opt\omit('wak')])
            ->willReturn($batches);

        /** @var BulkWriteResult|MockObject $deleteResult */
        $writeResult1 = $this->createMock(BulkWriteResult::class);
        $writeResult1->expects($this->atLeastOnce())->method('getDeletedCount')->willReturn(2);
        $writeResult1->expects($this->atLeastOnce())->method('getInsertedCount')->willReturn(9);
        $writeResult1->expects($this->atLeastOnce())->method('getMatchedCount')->willReturn(42);
        $writeResult1->expects($this->atLeastOnce())->method('getModifiedCount')->willReturn(19);
        $writeResult1->expects($this->atLeastOnce())->method('getUpsertedCount')->willReturn(31);
        $writeResult1->expects($this->atLeastOnce())->method('isAcknowledged')->willReturn(false);
        $writeResult1->expects($this->once())->method('getInsertedIds')->willReturn([3 => 42]);
        $writeResult1->expects($this->once())->method('getUpsertedIds')->willReturn([0 => 10, 2 => 13]);

        /** @var BulkWriteResult|MockObject $deleteResult */
        $writeResult2 = $this->createMock(BulkWriteResult::class);
        $writeResult2->expects($this->atLeastOnce())->method('getDeletedCount')->willReturn(1);
        $writeResult2->expects($this->atLeastOnce())->method('getInsertedCount')->willReturn(37);
        $writeResult2->expects($this->atLeastOnce())->method('getMatchedCount')->willReturn(8);
        $writeResult2->expects($this->atLeastOnce())->method('getModifiedCount')->willReturn(2);
        $writeResult2->expects($this->atLeastOnce())->method('getUpsertedCount')->willReturn(4);
        $writeResult2->expects($this->any())->method('isAcknowledged')->willReturn(false);
        $writeResult2->expects($this->once())->method('getInsertedIds')->willReturn([1 => 43]);
        $writeResult2->expects($this->once())->method('getUpsertedIds')->willReturn([0 => 17]);

        $this->collection->expects($this->exactly(2))->method("bulkWrite")
            ->withConsecutive([$batches[0], ['ordered' => false]], [$batches[1], ['ordered' => false]])
            ->willReturnOnConsecutiveCalls($writeResult1, $writeResult2);

        $expectedResult = new Result([3 => ['id' => 10000]]);

        $this->resultBuilder->expects($this->once())->method('with')
            ->with($this->callback(function (iterable $new) {
                $expected = [
                    0 => ['_id' => 10],
                    2 => ['_id' => 13],
                    3 => ['_id' => 42],
                    4 => ['_id' => 17],
                    5 => ['_id' => 43]
                ];
                $actual = i\iterable_to_array($new, true);

                $this->assertSame($expected, $actual);
                return true;
            }))
            ->willReturn($expectedResult);

        $result = $this->writer->save($documents, [opt\omit('wak')]);

        $expectedMeta = [
            'count' => 105,
            'deletedCount' => 3,
            'insertedCount' => 46,
            'matchedCount' => 50,
            'modifiedCount' => 21,
            'upsertedCount' => 35,
            'acknowledged' => false
        ];
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals($expectedResult->toArray(), $result->toArray());
        $this->assertEquals($expectedMeta, $result->getMeta());
    }

    public function oneOrManyProvider()
    {
        return [
            'One' => ['One', 1, [opt\limit(1)]],
            'Many' => ['Many', 10, []],
        ];
    }

    /**
     * @dataProvider oneOrManyProvider
     */
    public function testUpdate(string $oneOrMany, int $count, array $opts)
    {
        $filterQuery = $this->createMock(FilterQuery::class);
        $filterQuery->expects($this->once())->method('toArray')
            ->willReturn(['foo' => 42, 'color' => ['$ne' => 'blue']]);
        $filterQuery->expects($this->once())->method('getOptions')
            ->willReturn(['ack' => false]);

        $this->filterQueryBuilder->expects($this->once())->method('buildQuery')
            ->with(['foo' => 42, 'color(not)' => 'blue'], $opts)
            ->willReturn($filterQuery);

        $updateQuery = $this->createMock(FilterQuery::class);
        $updateQuery->expects($this->once())->method('toArray')
            ->willReturn(['$set' => ['color' => 'green']]);
        $updateQuery->expects($this->once())->method('getOptions')
            ->willReturn(['w' => 1]);

        $this->updateQueryBuilder->expects($this->once())->method('buildQuery')
            ->with([update\set('color', 'green')], $opts)
            ->willReturn($updateQuery);

        /** @var UpdateResult|MockObject $updateResult */
        $updateResult = $this->createMock(UpdateResult::class);
        $updateResult->expects($this->atLeastOnce())->method('getMatchedCount')->willReturn($count === 1 ? 0 : 21);
        $updateResult->expects($this->atLeastOnce())->method('getModifiedCount')->willReturn($count === 1 ? 0 : 6);
        $updateResult->expects($this->atLeastOnce())->method('getUpsertedCount')->willReturn($count === 1 ? 1 : 4);
        $updateResult->expects($this->atLeastOnce())->method('isAcknowledged')->willReturn(false);
        $updateResult->expects($this->once())->method('getUpsertedId')->willReturn($count === 1 ? 12 : null);

        $this->collection->expects($this->once())->method("update{$oneOrMany}")
            ->with(
                ['foo' => 42, 'color' => ['$ne' => 'blue']],
                ['$set' => ['color' => 'green']],
                ['ack' => false, 'w' => 1]
            )
            ->willReturn($updateResult);

        $expectedResult = new Result([['id' => 1000]]);

        $this->resultBuilder->expects($this->once())->method('with')
            ->with($this->callback(function (iterable $new) use ($count) {
                $actual = i\iterable_to_array($new, true);
                $this->assertSame($count === 1 ? [['_id' => 12]] : [], $actual);
                return true;
            }))
            ->willReturn($expectedResult);

        $result = $this->writer->update(
            ['foo' => 42, 'color(not)' => 'blue'],
            [update\set('color', 'green')],
            $opts
        );

        $expectedMeta = [
            'count' => $count,
            'matchedCount' => $count === 1 ? 0 : 21,
            'modifiedCount' => $count === 1 ? 0 : 6,
            'upsertedCount' => $count === 1 ? 1 : 4,
            'acknowledged' => false
        ];
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals($expectedResult->toArray(), $result->toArray());
        $this->assertEquals($expectedMeta, $result->getMeta());
    }

    /**
     * @dataProvider oneOrManyProvider
     */
    public function testDelete(string $oneOrMany, int $count, array $opts)
    {
        $query = $this->createMock(FilterQuery::class);
        $query->expects($this->once())->method('toArray')
            ->willReturn(['foo' => 42, 'color' => ['$ne' => 'blue']]);
        $query->expects($this->once())->method('getOptions')
            ->willReturn(['ack' => false, 'w' => 1]);

        $this->filterQueryBuilder->expects($this->once())->method('buildQuery')
            ->with(['foo' => 42, 'color(not)' => 'blue'], $opts)
            ->willReturn($query);

        /** @var DeleteResult|MockObject $deleteResult */
        $deleteResult = $this->createMock(DeleteResult::class);
        $deleteResult->expects($this->atLeastOnce())->method('getDeletedCount')->willReturn($count);
        $deleteResult->expects($this->atLeastOnce())->method('isAcknowledged')->willReturn(false);

        $this->collection->expects($this->once())->method("delete{$oneOrMany}")
            ->with(['foo' => 42, 'color' => ['$ne' => 'blue']], ['ack' => false, 'w' => 1])
            ->willReturn($deleteResult);

        $expectedResult = new Result([]);

        $this->resultBuilder->expects($this->once())->method('with')->willReturn($expectedResult);

        $result = $this->writer->delete(['foo' => 42, 'color(not)' => 'blue'], $opts);

        $expectedMeta = [
            'count' => $count,
            'deletedCount' => $count,
            'acknowledged' => false
        ];
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals($expectedResult->toArray(), $result->toArray());
        $this->assertEquals($expectedMeta, $result->getMeta());
    }


    public function testUpdateSeven()
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage("MongoDB can update one document or all documents, but not exactly 7");

        $this->filterQueryBuilder->expects($this->once())->method('buildQuery')->willReturn(new FilterQuery());
        $this->updateQueryBuilder->expects($this->once())->method('buildQuery')->willReturn(new FilterQuery());

        $this->collection->expects($this->never())->method("deleteOne");
        $this->collection->expects($this->never())->method("deleteMany");

        $this->writer->update([], [], [opt\limit(7)]);
    }
}
