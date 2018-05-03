<?php

namespace Jasny\DB\Mongo\DataMapper;

use Jasny\DB\Mongo\DB,
    Jasny\DB\Mongo\Collection,
    Jasny\DB\Mongo\TestEntity,
    Jasny\DB\Mongo\TestHelper,
    Jasny\DB\Entity,
    Jasny\DB\Entity\LazyLoading,
    Jasny\DB\Mongo\TestDataMapper,
    Jasny\DB\Mongo\TestEntityDataMapper,
    Jasny\DB\Mongo\TestEntityLazySimpleId,
    Jasny\DB\Mongo\TestEntityMeta,
    Jasny\DB\Mongo\TestEntityData;

/**
 * @covers Jasny\DB\Mongo\DataMapper\Implementation
 */
class ImplementationTest extends TestHelper
{
    /**
     * Test 'getFieldMap' method
     */
    public function testGetFieldMap()
    {
        $mapper = $this->createMock(TestDataMapper::class);
        $result = $this->callProtectedMethod($mapper, 'getFieldMap', []);

        $this->assertSame(['_id' => 'id'], $result);
    }

    /**
     * Test 'getEntityClass' method, if entity class is set as static variable
     */
    public function testGetEntityClassSet()
    {
        TestDataMapper::$entityClass = 'FooEntity';

        $mapper = $this->createMock(TestDataMapper::class);
        $result = $this->callProtectedMethod($mapper, 'getEntityClass', []);

        $this->assertSame('FooEntity', $result);
    }

    /**
     * Test 'getEntityClass' method, if class is not Entity
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Unable to determine entity class
     */
    public function testGetEntityClassException()
    {
        TestDataMapper::$entityClass = null;

        $mapper = new TestDataMapper(); // Do not use mock here, to enter 'if' block {substr(get_called_class(), -6) === 'Mapper'}
        $result = $this->callProtectedMethod($mapper, 'getEntityClass', []);
    }

    /**
     * Test 'getEntityClass' method, when autodetermining class
     */
    public function testGetEntityClass()
    {
        TestEntityDataMapper::$entityClass = null;

        $mapper = new TestEntityDataMapper(); // Do not use mock here, to be able to use {substr(get_called_class(), -6) === 'Mapper'}
        $result = $this->callProtectedMethod($mapper, 'getEntityClass', []);

        $this->assertSame(TestEntityData::class, $result);
    }

    /**
     * Test 'toData' method
     */
    public function testToData()
    {
        $values = ['foo' => 'bar', 'boo' => 'zoo'];

        $entity = $this->createPartialMock(TestEntity::class, ['getValues']);
        $entity->expects($this->once())->method('getValues')->willReturn($values);

        $mapper = $this->createPartialMock(TestDataMapper::class, []);
        $result = $this->callProtectedMethod($mapper, 'toData', [$entity]);

        $this->assertSame($values, $result);
    }

    /**
     * Test 'save' method, if document is ghost
     *
     * @expectedException \Exception
     * @expectedExceptionMessageRegExp /Unable to save: This \w+ entity isn't fully loaded. First expand, than edit, than save./
     */
    public function testSaveException()
    {
        $entity = $this->createMock(LazyLoading::class);
        $entity->expects($this->once())->method('isGhost')->willReturn(true);

        TestDataMapper::save($entity);
    }

    /**
     * Test 'save' method
     */
    public function testSave()
    {
        $saveResult = 'foo_result';

        $entity = $this->createPartialMock(TestEntityLazySimpleId::class, ['isGhost']);
        $entity->expects($this->once())->method('isGhost')->willReturn(false);

        $collection = $this->createMock(Collection::class);
        TestDataMapper::$collectionMock = $collection;

        $collection->expects($this->once())->method('save')->with($entity)->willReturn($saveResult);
        $collection->expects($this->once())->method('useResultId')->with($entity, 'id', $saveResult);

        TestDataMapper::save($entity);
    }

    /**
     * Test 'delete' method, if entity is not Identifiable
     *
     * @expectedException \Exception
     * @expectedExceptionMessageRegExp /A \w+ isn't identifiable/
     */
    public function testDeleteException()
    {
        $entity = $this->createMock(LazyLoading::class);

        TestDataMapper::delete($entity);
    }

    /**
     * Test 'delete' method
     */
    public function testDelete()
    {
        $entity = $this->createPartialMock(TestEntityMeta::class, ['getId']);
        $entity->expects($this->once())->method('getId')->willReturn('a');

        $collection = $this->createMock(Collection::class);
        TestDataMapper::$collectionMock = $collection;

        $collection->expects($this->once())->method('deleteOne')->with(['id' => 'a']);

        TestDataMapper::delete($entity);
    }
}
