<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Mongo\DB,
    Jasny\DB\Mongo\TestEntity,
    Jasny\DB\BasicEntity,
    Jasny\DB\Entity,
    Jasny\DB\Entity\Identifiable,
    Jasny\DB\Entity\LazyLoading,
    Jasny\DB\EntitySet;

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
        $db = $this->createMock(\MongoDB::class);
        $collection = new Collection($db, 'foo_name', \stdClass::class);
    }

    /**
     * Test 'createIndexes' method
     */
    public function testCreateIndexes()
    {
        $indexes = [
            ['foo' => 'bar', '$options' => ['delete' => true]],
            ['free' => 'see'],
            ['zoo' => 'baz', '$options' => ['some' => 'pam']]
        ];

        $collection = $this->createPartialMock(collection::class, ['deleteIndex', 'createIndex']);
        $collection->expects($this->once())->method('deleteIndex')->with(['foo' => 'bar']);
        $collection->expects($this->exactly(2))->method('createIndex')->withConsecutive(
            [['free' => 'see']],
            [['zoo' => 'baz'], ['some' => 'pam']]
        );

        $collection->createIndexes($indexes);
    }

    /**
     * Test 'createIndex' method with no deletion or exception
     */
    public function testCreateIndex()
    {
        $keys = ['foo' => 'bar'];
        $options = ['zoo' => 'baz'];
        $expected = ['keys' => $keys, 'options' => $options];

        $callback = function($keys, $options) use ($expected) {
            return $expected; // Test that correct parameters are passed
        };

        $collection = $this->createPartialMock(Collection::class, ['getCreateIndexMethod']);
        $collection->expects($this->once())->method('getCreateIndexMethod')->willReturn($callback);

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
            [new \MongoCursorException('', 1), ['ignore' => true, 'force' => true]], //Not 85 exception code
            [new \MongoCursorException('', 85), []]
        ];
    }

    /**
     * Test 'createIndex' method with throwing exception
     *
     * @dataProvider createIndexExceptionProvider
     * @param MongoCursorException $exception
     * @param array $options
     */
    public function testCreateIndexException($exception, $options)
    {
        $callback = function($keys, $options) use ($exception) {
            throw $exception;
        };

        $collection = $this->createPartialMock(Collection::class, ['getCreateIndexMethod']);
        $collection->expects($this->once())->method('getCreateIndexMethod')->willReturn($callback);

        $this->expectException(\MongoCursorException::class);
        $collection->createIndex(['foo' => 'bar'], $options);
    }

    /**
     * Test 'createIndex' method with deleting existing index
     */
    public function testCreateIndexForce()
    {
        $keys = ['foo' => 'bar'];
        $options = ['force' => true];
        $callbackState = (object)['called' => 0];

        $callback = function($keysArg, $optionsArg) use ($callbackState, $keys, $options) {
            $this->assertEquals($keys, $keysArg);
            $this->assertEquals($options, $optionsArg);

            if ($callbackState->called) {
                $callbackState->called++;
                return true;
            }

            $callbackState->called++;
            throw new \MongoCursorException('', 85);
        };

        $callback = $callback->bindTo($this);

        $collection = $this->createPartialMock(Collection::class, ['getCreateIndexMethod', 'deleteIndex']);
        $collection->expects($this->once())->method('getCreateIndexMethod')->willReturn($callback);
        $collection->expects($this->once())->method('deleteIndex')->with($keys);

        $result = $collection->createIndex($keys, $options);
        $this->assertEquals(true, $result);
        $this->assertEquals(2, $callbackState->called);
    }

    /**
     * Test 'createIndex' method with deleting existing index
     */
    public function testCreateIndexIgnore()
    {
        $keys = ['foo' => 'bar'];
        $options = ['ignore' => true];
        $callbackState = (object)['called' => 0];

        $callback = function($keysArg, $optionsArg) use ($callbackState, $keys, $options) {
            $this->assertEquals($keys, $keysArg);
            $this->assertEquals($options, $optionsArg);

            $callbackState->called++;
            throw new \MongoCursorException('', 85);
        };

        $callback = $callback->bindTo($this);

        $collection = $this->createPartialMock(Collection::class, ['getCreateIndexMethod', 'deleteIndex']);
        $collection->expects($this->once())->method('getCreateIndexMethod')->willReturn($callback);
        $collection->expects($this->never())->method('deleteIndex');

        $result = $collection->createIndex($keys, $options);
        $this->assertEquals(false, $result);
        $this->assertEquals(1, $callbackState->called);
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
        $date = $this->createMock(\MongoDate::class);
        $this->setPrivateProperty($date, 'sec', 123);

        $values = ['date' => $date, 'zoo' => 'boo'];

        $collection = $this->createPartialMock(Collection::class, []);
        $this->setPrivateProperty($collection, 'documentClass', $class);

        $db = $this->createPartialMock(DB::class, []);
        $collection->db = $db;

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
        $date = $this->createMock(\MongoDate::class);
        $this->setPrivateProperty($date, 'sec', 123);

        $values = ['date' => $date, 'zoo' => 'boo'];

        $collection = $this->createPartialMock(Collection::class, []);
        $this->setPrivateProperty($collection, 'documentClass', TestEntity::class);

        $db = $this->createPartialMock(DB::class, []);
        $collection->db = $db;

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


}
