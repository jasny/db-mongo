<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Mongo\DB,
    Jasny\DB\Mongo\TestEntity,
    Jasny\DB\Mongo\TypeCast\DeepCast,
    Jasny\DB\BasicEntity,
    Jasny\DB\Entity,
    Jasny\DB\Entity\Identifiable,
    Jasny\DB\Entity\LazyLoading,
    Jasny\DB\EntitySet,
    MongoDB\Driver\Manager;

/**
 * @covers Jasny\DB\Mongo\Collection
 */
class CollectionTest extends TestHelper
{
    /**
     * Test creating collection with document class, that is not Entity
     *
     * @expectedException LogicException
     */
    public function testConstructorExceptionEntity()
    {
        $manager = new Manager('mongodb://test-host');
        $collection = new Collection($manager, 'test-db', 'test-collection', ['documentClass' => \stdClass::class]);
    }

    /**
     * Provide data for testing 'createIndex' method
     *
     * @return array
     */
    public function createIndexProvider()
    {
        return [
            [['foo' => 1]],
            [(object)['foo' => 1]]
        ];
    }

    /**
     * Test 'createIndex' method with no deletion or exception
     *
     * @dataProvider createIndexProvider
     */
    public function testCreateIndex($keys)
    {
        $options = ['zoo' => 'baz'];
        $expected = 'foo_result';

        $collection = $this->createPartialMock(Collection::class, ['parent']);
        $collection->expects($this->once())->method('parent', (array)$keys, $options)->willReturn($expected);

        $result = $collection->createIndex($keys, $options);

        $this->assertEquals($expected, $result);
    }

    /**
     * Provide data for testing 'createIndex' method with exception
     *
     * @return array
     */
    public function createIndexExceptionProvider()
    {
        return [
            [new \MongoDB\Driver\Exception\RuntimeException('', 1), ['ignore' => true, 'force' => true]], //Not 85 (index exists) exception code
            [new \MongoDB\Driver\Exception\RuntimeException('', 85), []]
        ];
    }

    /**
     * Test 'createIndex' method with throwing exception
     *
     * @dataProvider createIndexExceptionProvider
     * @param \MongoDB\Driver\Exception\RuntimeException $exception
     * @param array $options
     */
    public function testCreateIndexException($exception, $options)
    {
        $keys = ['foo' => 1];

        $collection = $this->createPartialMock(Collection::class, ['parent']);
        $collection->expects($this->once())->method('parent', $keys, $options)->willThrowException($exception);

        $this->expectException(\MongoDB\Driver\Exception\RuntimeException::class);
        $collection->createIndex($keys, $options);
    }

    /**
     * Test 'createIndex' method with deleting existing index
     *
     * @dataProvider createIndexProvider
     */
    public function testCreateIndexForce($keys)
    {
        $options = ['force' => true];
        $callbackState = (object)['called' => 0];

        $callback = function($keysArg, $optionsArg) use ($callbackState) {
            if ($callbackState->called) {
                $callbackState->called++;
                return true;
            }

            $callbackState->called++;
            throw new \MongoDB\Driver\Exception\RuntimeException('', 85);
        };

        $collection = $this->createPartialMock(Collection::class, ['parent', 'dropIndex']);
        $collection->expects($this->exactly(2))->method('parent', (array)$keys, $options)->will($this->returnCallback($callback));
        $collection->expects($this->once())->method('dropIndex')->with('foo');

        $result = $collection->createIndex($keys, $options);
        $this->assertEquals(true, $result);
        $this->assertEquals(2, $callbackState->called);
    }

    /**
     * Test 'createIndex' method with deleting existing index
     *
     * @dataProvider createIndexProvider
     */
    public function testCreateIndexIgnore($keys)
    {
        $options = ['ignore' => true];

        $callback = function($keysArg, $optionsArg) {
            throw new \MongoDB\Driver\Exception\RuntimeException('', 85);
        };

        $collection = $this->createPartialMock(Collection::class, ['parent', 'dropIndex']);
        $collection->expects($this->once())->method('parent', (array)$keys, $options)->will($this->returnCallback($callback));
        $collection->expects($this->never())->method('dropIndex');

        $result = $collection->createIndex($keys, $options);
        $this->assertEquals(false, $result);
    }

    /**
     * Test 'getDocumentClass' method
     */
    public function testGetDocumentClass()
    {
        $collection = $this->createPartialMock(Collection::class, []);
        $this->setPrivateProperty($collection, 'documentClass', 'foo');

        $result = $collection->getDocumentClass();

        $this->assertEquals('foo', $result);
    }

    /**
     * Test 'withoutCasting' method
     */
    public function testWithoutCasting()
    {
        $manager = new Manager('mongodb://test-host');

        $collection = $this->createPartialMock(Collection::class, ['getManager', 'getDatabaseName', 'getCollectionName']);
        $collection->expects($this->once())->method('getManager')->willReturn($manager);
        $collection->expects($this->once())->method('getDatabaseName')->willReturn('test-db');
        $collection->expects($this->once())->method('getCollectionName')->willReturn('test-collection');
        $this->setPrivateProperty($collection, 'documentClass', 'SomeClass');

        $result = $collection->withoutCasting();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame($manager, $result->getManager());
        $this->assertSame('test-db', $result->getDatabaseName());
        $this->assertSame('test-collection', $result->getCollectionName());
        $this->assertSame(null, $result->getDocumentClass());
    }

    /**
     * Provide data for testing 'insertOne' method
     *
     * @return array
     */
    public function insertOneProvider()
    {
        return [
            [['foo' => 'bar']],
            [(object)['foo' => 'bar']],
        ];
    }

    /**
     * Test 'insertOne' method
     *
     * @dataProvider insertOneProvider
     */
    public function testInsertOne($document)
    {
        $options = ['opt1' => 'val1'];
        $values = (array)$document;
        $queryResult = $this->createMock(\MongoDB\InsertOneResult::class);

        $typeCast = $this->createMock(DeepCast::class);

        $collection = $this->createPartialMock(Collection::class, ['getTypeCaster', 'parent']);
        $collection->expects($this->once())->method('getTypeCaster')->willReturn($typeCast);
        $typeCast->expects($this->once())->method('toMongoType')->with($document, true)->willReturn($values);
        $collection->expects($this->once())->method('parent')->with('insertOne', $values, $options)->willReturn($queryResult);

        $result = $collection->insertOne($document, $options);
        $this->assertSame($queryResult, $result);
    }

    /**
     * Test 'insertMany' method
     */
    public function testInsertMany()
    {
        $options = ['opt1' => 'val1'];
        $docs = [
            ['foo' => 'bar'],
            ['foo2' => 'bar2'],
            (object)['foo3' => 'bar3']
        ];

        $data = [
            ['foo' => 'bar'],
            ['foo2' => 'bar2'],
            ['foo3' => 'bar3']
        ];

        $queryResult = $this->createMock(\MongoDB\InsertManyResult::class);

        $typeCast = $this->createMock(DeepCast::class);
        $typeCast->expects($this->exactly(3))->method('toMongoType')->will($this->returnValueMap([
            [$docs[0], true, $docs[0]],
            [$docs[1], true, $docs[1]],
            [$docs[2], true, (array)$docs[2]]
        ]));

        $collection = $this->createPartialMock(Collection::class, ['getTypeCaster', 'parent']);
        $collection->expects($this->once())->method('getTypeCaster')->willReturn($typeCast);
        $collection->expects($this->once())->method('parent')->with('insertMany', $data, $options)->willReturn($queryResult);

        $result = $collection->insertMany($docs, $options);
        $this->assertSame($queryResult, $result);
    }

    /**
     * Test 'replaceOne' method
     *
     * @dataProvider insertOneProvider
     */
    public function testReplaceOne($document)
    {
        $options = ['opt1' => 'val1'];
        $filter = ['match_key' => 'val'];
        $values = (array)$document;
        $queryResult = $this->createMock(\MongoDB\UpdateResult::class);

        $typeCast = $this->createMock(DeepCast::class);

        $collection = $this->createPartialMock(Collection::class, ['getTypeCaster', 'parent']);
        $collection->expects($this->once())->method('getTypeCaster')->willReturn($typeCast);
        $typeCast->expects($this->once())->method('toMongoType')->with($document, true)->willReturn($values);
        $collection->expects($this->once())->method('parent')->with('replaceOne', $filter, $values, $options)->willReturn($queryResult);

        $result = $collection->replaceOne($filter, $document, $options);
        $this->assertSame($queryResult, $result);
    }

    /**
     * Provide data for testing 'save' method, when 'replaceOne' method is called
     *
     * @return array
     */
    public function saveReplaceProvider()
    {
        return [
            [['key1' => 'val1'], ['key1' => 'val1', 'upsert' => true]],
            [['key1' => 'val1', 'upsert' => true], ['key1' => 'val1', 'upsert' => true]],
            [['key1' => 'val1', 'upsert' => false], ['key1' => 'val1', 'upsert' => false]]
        ];
    }

    /**
     * Test 'save' method, when 'replaceOne' method is called
     *
     * @dataProvider saveReplaceProvider
     */
    public function testSaveReplace($options, $useOptions)
    {
        $document = (object)['foo' => 'bar', '_id' => 'baz'];
        $filter = ['_id' => 'baz'];
        $expected = 'foo_result';
        $values = $document;

        $typeCast = $this->createMock(DeepCast::class);

        $collection = $this->createPartialMock(Collection::class, ['getTypeCaster', 'replaceOne', 'insertOne', 'useResultId']);
        $collection->expects($this->once())->method('getTypeCaster')->willReturn($typeCast);
        $typeCast->expects($this->once())->method('toMongoType')->with($document, true)->willReturn($values);
        $collection->expects($this->once())->method('replaceOne')->with($filter, $document, $useOptions)->willReturn($expected);
        $collection->expects($this->once())->method('useResultId')->with($document, '_id', $expected);
        $collection->expects($this->never())->method('insertOne');

        $result = $collection->save($document, $options);
        $this->assertSame($expected, $result);
    }

    /**
     * Test 'save' method, when 'insertOne' method is called
     */
    public function testSaveInsert()
    {
        $document = (object)['foo' => 'bar'];
        $options = ['opt1' => 'val1'];
        $expected = 'foo_result';
        $values = $document;

        $typeCast = $this->createMock(DeepCast::class);

        $collection = $this->createPartialMock(Collection::class, ['getTypeCaster', 'replaceOne', 'insertOne', 'useResultId']);
        $collection->expects($this->once())->method('getTypeCaster')->willReturn($typeCast);
        $typeCast->expects($this->once())->method('toMongoType')->with($document, true)->willReturn($values);
        $collection->expects($this->once())->method('insertOne')->with($document, $options)->willReturn($expected);
        $collection->expects($this->once())->method('useResultId')->with($document, '_id', $expected);
        $collection->expects($this->never())->method('replaceOne');

        $result = $collection->save($document, $options);
        $this->assertSame($expected, $result);
    }

    /**
     * Provide data for testing 'asDocument' method
     *
     * @return array
     */
    public function asDocumentProvider()
    {
        return [
            [TestEntity::class, false],
            [TestEntityLazy::class, true]
        ];
    }

    /**
     * Test 'asDocument' method
     *
     * @dataProvider asDocumentProvider
     */
    public function testAsDocument($class, $lazy)
    {
        $date = new \MongoDB\BSON\UTCDateTime(123000);
        $values = ['date' => $date, 'zoo' => 'boo'];

        $typeCast = $this->createMock(DeepCast::class);

        $collection = $this->createPartialMock(Collection::class, ['getTypeCaster']);
        $collection->expects($this->once())->method('getTypeCaster')->willReturn($typeCast);
        $typeCast->expects($this->exactly(2))->method('fromMongoType')->will($this->returnValueMap([
            [$values['date'], $date->toDateTime()],
            [$values['zoo'], $values['zoo']],
        ]));
        $this->setPrivateProperty($collection, 'documentClass', $class);

        $result = $collection->asDocument($values, $lazy);

        $this->assertInstanceOf($class, $result);
        $this->assertInstanceOf(\DateTime::class, $result->date);
        $this->assertEquals(123, $result->date->getTimestamp());
        $this->assertEquals('boo', $result->zoo);
    }

    /**
     * Test 'asDocument' method with wrong lazy param
     *
     * @expectedException LogicException
     * @expectedExceptionMessage Jasny\DB\Entity doesn't support lazy loading. All fields are required to create the entity.
     */
    public function testAsDocumentLazyException()
    {
        $date = new \MongoDB\BSON\UTCDateTime(123000);
        $values = ['date' => $date, 'zoo' => 'boo'];

        $collection = $this->createPartialMock(Collection::class, []);
        $this->setPrivateProperty($collection, 'documentClass', TestEntity::class);

        $result = $collection->asDocument($values, true);
    }

    /**
     * Test 'asDocument' method with empty documentClass
     *
     * @expectedException LogicException
     * @expectedExceptionMessage Document class not set
     */
    public function testAsDocumentEmptyDocumentClass()
    {
        $collection = $this->createPartialMock(Collection::class, []);
        $collection->asDocument(['foo' => 'bar']);
    }

    /**
     * Test 'asDocument' method with wrong documentClass
     *
     * @expectedException LogicException
     * @expectedExceptionMessage Document class should implement the Jasny\DB\Entity interface
     */
    public function testAsDocumentWrongDocumentClass()
    {
        $collection = $this->createPartialMock(Collection::class, []);
        $this->setPrivateProperty($collection, 'documentClass', CollectionTest::class);

        $collection->asDocument(['foo' => 'bar']);
    }

    /**
     * Provide data for testing 'find' method
     *
     * @return array
     */
    public function findProvider()
    {
        return [
            [[], false],
            [['foo' => 'bar'], false],
            [['projection' => []], false],
            [['projection' => ['field' => 1]], true]
        ];
    }

    /**
     * Test 'find' method
     *
     * @dataProvider findProvider
     */
    public function testFind($options, $expectedLazy)
    {
        $filter = [];
        $cursor = 'driver_cursor';
        $expected = 'result_cursor';

        $collection = $this->createPartialMock(Collection::class, ['parent', 'createCursor']);
        $collection->expects($this->once())->method('parent')->with('find', $filter, $options)->willReturn($cursor);
        $collection->expects($this->once())->method('createCursor')->with($cursor, $expectedLazy)->willReturn($expected);

        $result = $collection->find($filter, $options);
        $this->assertSame($expected, $result);
    }

    /**
     * Provide data for testing 'findOne' method
     *
     * @return array
     */
    public function findOneProvider()
    {
        return [
            [[], false],
            [['foo' => 'bar'], false],
            [['projection' => []], false],
            [['projection' => ['field' => 1]], true]
        ];
    }

    /**
     * Test 'findOne' method
     *
     * @dataProvider findOneProvider
     */
    public function testFindOne($options, $expectedLazy)
    {
        $filter = [];
        $values = [];
        $valuesAsDocument = ['foo'];
        $documentClass = 'FooClass';

        $collection = $this->createPartialMock(Collection::class, ['parent', 'asDocument']);
        $collection->expects($this->once())->method('parent')->with('findOne', $filter, $options)->willReturn($values);
        $collection->expects($this->once())->method('asDocument')->with($values, $expectedLazy)->willReturn($valuesAsDocument);
        $this->setPrivateProperty($collection, 'documentClass', $documentClass);

        $result = $collection->findOne($filter, $options);
        $this->assertSame($valuesAsDocument, $result);
    }

    /**
     * Provide data for testing 'findOne' method, when no cast is performed
     *
     * @return array
     */
    public function findOneNoCastProvider()
    {
        return [
            [null, 'FooClass'],
            [['foo'], null],
        ];
    }

    /**
     * Test 'findOne' method, when no casting is performed
     *
     * @dataProvider findOneNoCastProvider
     */
    public function testFindOneNoCast($values, $documentClass)
    {
        $filter = [];
        $options = [];

        $collection = $this->createPartialMock(Collection::class, ['parent', 'asDocument']);
        $collection->expects($this->once())->method('parent')->with('findOne', $filter, $options)->willReturn($values);
        $collection->expects($this->never())->method('asDocument');
        $this->setPrivateProperty($collection, 'documentClass', $documentClass);

        $result = $collection->findOne($filter, $options);
        $this->assertSame($values, $result);
    }

    /**
     * Provide data for testing 'useResultId' method, if single id is returned
     *
     * @return array
     */
    public function useResultIdSingleProvider()
    {
        return [
            [['foo' => 'bar'], $this->createMock(\MongoDB\InsertOneResult::class), 'getInsertedId'],
            [['foo' => 'bar'], $this->createMock(\MongoDB\UpdateResult::class), 'getUpsertedId'],
            [(object)['foo' => 'bar'], $this->createMock(\MongoDB\InsertOneResult::class), 'getInsertedId'],
            [(object)['foo' => 'bar'], $this->createMock(\MongoDB\UpdateResult::class), 'getUpsertedId'],
        ];
    }

    /**
     * Test 'useResultId' method, if single id is returned
     *
     * @dataProvider useResultIdSingleProvider
     */
    public function testUseResultIdSingle($document, $queryResult, $method)
    {
        $collection = $this->createPartialMock(Collection::class, []);
        $queryResult->expects($this->once())->method($method)->willReturn('a');

        $collection->useResultId($document, '_idCustom', $queryResult);

        $document = (array)$document;
        $this->assertSame('a', $document['_idCustom']);
    }

    /**
     * Provide data for testing 'useResultId' method, if multiple ids are returned
     *
     * @return array
     */
    public function useResultIdMultipleProvider()
    {
        return [
            [[['foo' => 'bar'], ['zoo' => 'baz']], $this->createMock(\MongoDB\InsertManyResult::class), 'getInsertedIds'],
            [[['foo' => 'bar'], ['zoo' => 'baz']], $this->createMock(\MongoDB\UpdateResult::class), 'getUpsertedId'],
            [[(object)['foo' => 'bar'], (object)['zoo' => 'baz']], $this->createMock(\MongoDB\InsertManyResult::class), 'getInsertedIds'],
            [[(object)['foo' => 'bar'], (object)['zoo' => 'baz']], $this->createMock(\MongoDB\UpdateResult::class), 'getUpsertedId'],
        ];
    }

    /**
     * Test 'useResultId' method, if multiple ids are returned
     *
     * @dataProvider useResultIdMultipleProvider
     */
    public function testUseResultIdMultiple($documents, $queryResult, $method)
    {
        $collection = $this->createPartialMock(Collection::class, []);
        $queryResult->expects($this->once())->method($method)->willReturn(['a', 'b']);

        $collection->useResultId($documents, '_idCustom', $queryResult);

        $documents[0] = (array)$documents[0];
        $documents[1] = (array)$documents[1];

        $this->assertSame('a', $documents[0]['_idCustom']);
        $this->assertSame('b', $documents[1]['_idCustom']);
    }

    /**
     * Provide data for testing 'useResultId' method, if no id is returned
     *
     * @return array
     */
    public function useResultIdEmptyProvider()
    {
        return [
            [null],
            [[]]
        ];
    }

    /**
     * Test 'useResultId' method, if no id is returned
     *
     * @dataProvider useResultIdEmptyProvider
     */
    public function testUseResultIdEmpty($id)
    {
        $document = ['foo' => 'bar'];

        $queryResult = $this->createMock(\MongoDB\UpdateResult::class);
        $queryResult->expects($this->once())->method('getUpsertedId')->willReturn($id);

        $collection = $this->createPartialMock(Collection::class, []);

        $collection->useResultId($document, '_idCustom', $queryResult);

        $this->assertEquals(['foo' => 'bar'], $document);
    }
}
