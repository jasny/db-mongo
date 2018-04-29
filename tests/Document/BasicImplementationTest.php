<?php

namespace Jasny\DB\Mongo\Document;

use Jasny\DB\Dataset\Sorted,
    Jasny\DB\Dataset\Sorted\Implementation as SortedImplementation,
    Jasny\DB\Entity,
    Jasny\DB\Data,
    Jasny\DB\Mongo\TestDocumentSorted,
    Jasny\DB\Mongo\TestDocumentLazy,
    Jasny\DB\Mongo\TestDocumentChangeAware,
    Jasny\DB\Mongo\TestDocumentSoftDeletion,
    Jasny\DB\Mongo\TestDocumentMetaSearch,
    Jasny\DB\Mongo\TestEntityMetaMongo,
    Jasny\DB\Mongo\TestEntityMeta,
    Jasny\DB\Mongo\TestHelper,
    Jasny\DB\Mongo\Collection,
    Jasny\DB\Entity\Identifiable,
    Jasny\DB\EntitySet;

/**
 * @covers Jasny\DB\Mongo\Document\BasicImplementation
 */
class BasicImplementationTest extends TestHelper
{
    /**
     * Test 'getIdProperty' method
     */
    public function testGetIdProperty()
    {
        $result = BasicImplementation::getIdProperty();
        $this->assertEquals('id', $result);
    }

    /**
     * Test 'getId' method
     */
    public function testGetId()
    {
        $traitObj = $this->getMockForTrait(BasicImplementation::class);
        $traitObj->id = 'foo';

        $result = $traitObj->getId();

        $this->assertEquals('foo', $result);
    }

    /**
     * Test 'toData' method
     */
    public function testToData()
    {
        $document = $this->createPartialMock(TestDocumentSorted::class, []);

        $entity = $this->createMock(Identifiable::class);
        $entity->expects($this->any())->method('getId')->willReturn('a');

        $setIdentifiable = $this->createPartialMock(EntitySet::class, []);
        $subEntity1 = $this->createMock(Identifiable::class);
        $subEntity2 = $this->createMock(Identifiable::class);
        $subEntity1->expects($this->any())->method('getId')->willReturn('b');
        $subEntity2->expects($this->any())->method('getId')->willReturn('c');
        $this->setPrivateProperty($setIdentifiable, 'entities', [$subEntity1, $subEntity2]);
        $this->setPrivateProperty($setIdentifiable, 'entityClass', Identifiable::class);

        $setNotIdentifiable = $this->createPartialMock(EntitySet::class, ['toData']);
        $subEntity3 = $this->createMock(Entity::class);
        $subEntity4 = $this->createMock(Entity::class);
        $setNotIdentifiable->expects($this->any())->method('toData')->willReturn(['test' => 'test']);
        $this->setPrivateProperty($setNotIdentifiable, 'entities', [$subEntity3, $subEntity4]);
        $this->setPrivateProperty($setNotIdentifiable, 'entityClass', Entity::class);

        $data = $this->createMock(Data::class);
        $data->expects($this->any())->method('toData')->willReturn(['some' => 'data']);

        $document->_id = 'foo_id';
        $document->stringVar = 'test-rest';
        $document->arrayVar = ['foo' => 'bar', 'zoo' => 'baz'];
        $document->objectVar = (object)['foo1' => 'bar1', 'zoo1' => 'baz1'];
        $document->entityVar = $entity;
        $document->setIdentifiableVar = $setIdentifiable;
        $document->setNotIdentifiableVar = $setNotIdentifiable;
        $document->dataVar = $data;

        $result = $document->toData();
        $expected = [
            '_id' => 'foo_id',
            'stringVar' => 'test-rest',
            'arrayVar' => ['foo' => 'bar', 'zoo' => 'baz'],
            'objectVar' => (object)['foo1' => 'bar1', 'zoo1' => 'baz1'],
            'entityVar' => 'a',
            'setIdentifiableVar' => ['b', 'c'],
            'setNotIdentifiableVar' => ['test' => 'test'],
            'dataVar' => ['some' => 'data'],
            '_sort' => 'foo'
        ];

        $this->assertEquals($expected, $result);

        $document->_id = null;
        $result = $document->toData();
        unset($expected['_id']);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test 'childEntityToId' method
     */
    public function testChildEntityToId()
    {
        $entity = $this->createPartialMock(TestEntityMetaMongo::class, []);
        $traitObj = $this->getMockForTrait(BasicImplementation::class);

        $id = '5923c0e936b3940c14000029';
        $entity->id = $id;

        $result = $this->callProtectedMethod($traitObj, 'childEntityToId', [$entity]);

        $this->assertInstanceOf(\MongoDB\BSON\ObjectId::class, $result);
        $this->assertEquals($id, (string)$result);

        $entity = $this->createPartialMock(TestEntityMeta::class, []);
        $entity->id = $id;

        $result = $this->callProtectedMethod($traitObj, 'childEntityToId', [$entity]);
        $this->assertEquals($id, $result);
    }

    /**
     * Provide data for testing 'save' method for ChangeAware document
     *
     * @return array
     */
    public function saveChangeAwareProvider()
    {
        return [
            [true, 'insertOne'],
            [false, 'save']
        ];
    }

    /**
     * Test 'save' method for ChangeAware document
     *
     * @dataProvider saveChangeAwareProvider
     */
    public function testSaveChangeAware($isNew, $method)
    {
        $data = ['foo' => 'bar'];
        $options = ['zoo' => 'baz'];
        $queryResult = 'foo_object_result_with_id';

        $document = $this->createPartialMock(TestDocumentChangeAware::class, ['toData', 'cast', 'isNew']);
        $collection = $this->createMock(Collection::class);
        $document::$collectionMock = $collection;

        $document->expects($this->once())->method('toData')->willReturn($data);
        $document->expects($this->once())->method('isNew')->willReturn($isNew);
        $collection->expects($this->once())->method($method)->with($data, $options)->willReturn($queryResult);
        $collection->expects($this->once())->method('useResultId')->with($document, 'id', $queryResult);

        $document->save($options);
    }

    /**
     * Test 'save' method for non ChangeAware document
     */
    public function testSaveNotChangeAware()
    {
        $data = ['foo' => 'bar'];
        $options = ['zoo' => 'baz'];
        $queryResult = 'foo_object_result_with_id';

        $document = $this->createPartialMock(TestDocumentSoftDeletion::class, ['toData', 'cast', 'isNew']);
        $collection = $this->createMock(Collection::class);
        $document::$collectionMock = $collection;

        $document->expects($this->once())->method('toData')->willReturn($data);
        $collection->expects($this->once())->method('save')->with($data, $options)->willReturn($queryResult);
        $collection->expects($this->once())->method('useResultId')->with($document, 'id', $queryResult);

        $document->save($options);
    }

    /**
     * Test 'save' method, if exception is thrown
     *
     * @expectedException Exception
     * @expectedExceptionMessageRegExp /Unable to save: This \S+ entity isn't fully loaded. First expand, than edit, than save/
     */
    public function testSaveException()
    {
        $document = $this->createPartialMock(TestDocumentLazy::class, ['isGhost']);
        $document->expects($this->once())->method('isGhost')->willReturn(true);

        $document->save();
    }

    /**
     * Test 'jsonSerialize' method
     */
    public function testJsonSerialize()
    {
        $values = [
            new \DateTime('2010-12-30T12:00:00+0000'),
            new \MongoDB\BSON\ObjectId('5923c0e936b3940c14000029')
        ];

        $casted = (object)[
            '2010-12-30T12:00:00+0000',
            '5923c0e936b3940c14000029'
        ];

        $document = $this->createPartialMock(TestDocumentLazy::class, ['expand', 'getValues', 'jsonSerializeFilter']);
        $document->expects($this->once())->method('expand');
        $document->expects($this->once())->method('getValues')->willReturn($values);
        $document->expects($this->once())->method('jsonSerializeFilter')->with($casted)->willReturn('some_result');

        $result = $document->jsonSerialize();

        $this->assertEquals('some_result', $result);
    }

    /**
     * Provide data for testing 'fromData' method
     *
     * @return array
     */
    public function fromDataProvider()
    {
        return [
            [['foo' => 'bar']],
            [(object)['foo' => 'bar']]
        ];
    }

    /**
     * Test 'fromData' method
     *
     * @dataProvider fromDataProvider
     */
    public function testFromData($data)
    {
        $result = TestDocumentLazy::fromData($data);

        $this->assertInstanceOf(TestDocumentLazy::class, $result);
        $this->assertEquals('bar', $result->foo);
    }

    /**
     * Test 'delete' method
     */
    public function testDelete()
    {
        $options = ['zoo' => 'baz'];

        $document = $this->createPartialMock(TestDocumentMetaSearch::class, ['getId']);
        $collection = $this->createMock(Collection::class);
        $document::$collectionMock = $collection;

        $document->expects($this->once())->method('getId')->willReturn('a');
        $collection->expects($this->once())->method('deleteOne')->with(['_id' => 'a'], $options);

        $document->delete($options);
    }

    /**
     * Provide data for testing 'reload' method
     *
     * @return array
     */
    public function reloadProvider()
    {
        return [
            [['id' => 'a', 'foo' => 'bar', "\0potato" => 'baz']],
            [(object)['id' => 'a', 'foo' => 'bar', "\0potato" => 'baz']]
        ];
    }

    /**
     * Test 'reload' method
     *
     * @dataProvider reloadProvider
     */
    public function testReload($fetched)
    {
        $document = $this->createPartialMock(TestDocumentMetaSearch::class, []);
        $collection = $this->createMock(Collection::class);
        $document::$collectionMock = $collection;

        $document->id = 'a';
        $collection->expects($this->once())->method('findOne')->with(['_id' => 'a'])->willReturn($fetched);

        $result = $document->reload();

        $this->assertSame($document, $result);
        $this->assertSame('a', $document->id);
        $this->assertSame('bar', $document->foo);
        $this->assertEmpty($this->getPrivateProperty($document, 'potato'));
    }

    /**
     * Test 'reload' method, if empty document is fetched
     */
    public function testReloadEmpty()
    {
        $document = $this->createPartialMock(TestDocumentMetaSearch::class, []);
        $collection = $this->createMock(Collection::class);
        $document::$collectionMock = $collection;

        $document->id = 'a';
        $collection->expects($this->once())->method('findOne')->with(['_id' => 'a'])->willReturn(null);

        $result = $document->reload();

        $this->assertSame(false, $result);
    }

    /**
     * Provide data for testing 'hasUnique' method
     *
     * @return array
     */
    public function hasUniqueProvider()
    {
        return [
            [[], ['id(not)' => 'a', 'foo' => 'fooVal'], true],
            [[], ['id(not)' => 'a', 'foo' => 'fooVal'], false],
            [null, ['id(not)' => 'a', 'foo' => 'fooVal'], true],
            [null, ['id(not)' => 'a', 'foo' => 'fooVal'], false],
            [['nonExist'], ['id(not)' => 'a', 'foo' => 'fooVal'], true],
            [['nonExist'], ['id(not)' => 'a', 'foo' => 'fooVal'], false],
            [['bar', 'zoo', 'nonExist'], ['id(not)' => 'a', 'foo' => 'fooVal', 'bar' => 'barVal', 'zoo' => 'baz'], true],
            [['bar', 'zoo', 'nonExist'], ['id(not)' => 'a', 'foo' => 'fooVal', 'bar' => 'barVal', 'zoo' => 'baz'], false]
        ];
    }

    /**
     * Test 'hasUnique' method
     *
     * @dataProvider hasUniqueProvider
     */
    public function testHasUnique($group, $expectedFilter, $expected)
    {
        $checkCalled = (object)['count' => 0];

        $checkArgs = function($filter) use ($expectedFilter, $checkCalled) {
            $this->assertEquals($expectedFilter, $filter);
            $checkCalled->count++;
        };
        $checkArgs->bindTo($this);

        $options = ['fooReturn' => $expected, 'checkArgs' => $checkArgs];

        $document = $this->createPartialMock(TestDocumentMetaSearch::class, ['getId']);
        $document->expects($this->once())->method('getId')->willReturn('a');

        $document->foo = 'fooVal';
        $document->bar = 'barVal';
        $document->zoo = 'baz';

        $result = $document->hasUnique('foo', $group, $options);

        $this->assertEquals($expected, $result);
        $this->assertEquals(1, $checkCalled->count);
    }

    /**
     * Provide data for testing 'hasUnique' method, if property is not set
     *
     * @return array
     */
    public function hasUniqueEmptyProvider()
    {
        return [
            [null],
            [[]],
            [['foo']]
        ];
    }

    /**
     * Test 'hasUnique' method, if property is not set
     *
     * @dataProvider hasUniqueEmptyProvider
     */
    public function testHasUniqueEmpty($group)
    {
        $document = $this->createPartialMock(TestDocumentMetaSearch::class, []);

        $document->foo = 'fooVal';
        $document->bar = 'barVal';

        $result = $document->hasUnique('nonExist', $group);

        $this->assertSame(true, $result);
    }
}
