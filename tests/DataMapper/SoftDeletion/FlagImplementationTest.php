<?php

namespace Jasny\DB\Mongo\DataMapper\SoftDeletion;

use Jasny\DB\Mongo\DB,
    Jasny\DB\Mongo\Collection,
    Jasny\DB\Mongo\TestHelper,
    Jasny\DB\Mongo\TestEntity,
    Jasny\DB\Mongo\TestEntityMeta,
    Jasny\DB\Mongo\TestDataMapperSoftDeletion,
    Jasny\DB\BasicEntity,
    Jasny\DB\Entity,
    Jasny\DB\Entity\Identifiable,
    Jasny\DB\Entity\LazyLoading,
    Jasny\DB\EntitySet;

/**
 * @covers Jasny\DB\Mongo\DataMapper\SoftDeletion\FlagImplementation
 */
class FlagImplementationTest extends TestHelper
{
    /**
     * Provide data for testing 'filterToQuery' method
     *
     * @return array
     */
    public function filterToQueryProvider()
    {
        return [
            [[], ['foo' => 'bar', '_deleted' => null]],
            [['from-trash'], ['foo' => 'bar', '_deleted' => true]],
            [['include-trash'], ['foo' => 'bar']]
        ];
    }

    /**
     * Test 'filterToQuery' method
     *
     * @dataProvider filterToQueryProvider
     */
    public function testFilterToQuery($options, $expected)
    {
        $filter = ['foo' => 'bar'];
        $mapper = $this->createPartialMock(TestDataMapperSoftDeletion::class, []);

        $result = $this->callProtectedMethod($mapper, 'filterToQuery', [$filter, $options]);

        $this->assertSame($expected, $result);
    }

    /**
     * Provide data for testing 'isDeleted' method
     *
     * @return array
     */
    public function isDeletedProvider()
    {
        return [
            [1, true],
            [0, false]
        ];
    }

    /**
     * Test 'isDeleted' method
     *
     * @dataProvider isDeletedProvider
     * @param int $count
     * @param boolean $expected
     */
    public function testIsDeleted($count, $expected)
    {
        $query = ['_id' => 'a', '_deleted' => true];

        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('count')->with($query)->willReturn($count);

        $entity = $this->createPartialMock(TestEntityMeta::class, ['getId']);
        $entity->expects($this->once())->method('getId')->willReturn('a');

        TestDataMapperSoftDeletion::$collectionMock = $collection;
        $result = TestDataMapperSoftDeletion::isDeleted($entity);

        $this->assertSame($expected, $result);
    }

    /**
     * Test 'isDeleted' method, if entity has no id
     */
    public function testIsDeletedNoId()
    {
        $query = ['_id' => null, '_deleted' => true];

        $entity = $this->createPartialMock(TestEntityMeta::class, ['getId']);
        $entity->expects($this->once())->method('getId')->willReturn(null);

        $result = TestDataMapperSoftDeletion::isDeleted($entity);

        $this->assertSame(false, $result);
    }

    /**
     * Test 'delete' method
     */
    public function testDelete()
    {
        $query = ['_id' => 'a', '_deleted' => null];

        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('updateOne')->with($query, ['$set' => ['_deleted' => true]])->willReturn(true);

        $entity = $this->createPartialMock(TestEntityMeta::class, ['getId']);
        $entity->expects($this->once())->method('getId')->willReturn('a');

        TestDataMapperSoftDeletion::$collectionMock = $collection;
        $result = TestDataMapperSoftDeletion::delete($entity);

        $this->assertSame(true, $result);
    }

    /**
     * Test 'undelete' method
     */
    public function testUndelete()
    {
        $query = ['_id' => 'a', '_deleted' => true];

        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('updateOne')->with($query, ['$unset' => ['_deleted' => 1]])->willReturn(true);

        $entity = $this->createPartialMock(TestEntityMeta::class, ['getId']);
        $entity->expects($this->once())->method('getId')->willReturn('a');

        TestDataMapperSoftDeletion::$collectionMock = $collection;
        $result = TestDataMapperSoftDeletion::undelete($entity);

        $this->assertSame(true, $result);
    }

    /**
     * Test 'purge' method
     */
    public function testPurge()
    {
        $query = ['_id' => 'a', '_deleted' => true];

        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('count')->with($query)->willReturn(1);
        $collection->expects($this->once())->method('deleteOne')->with($query)->willReturn(true);

        $entity = $this->createPartialMock(TestEntityMeta::class, ['getId']);
        $entity->method('getId')->willReturn('a');

        TestDataMapperSoftDeletion::$collectionMock = $collection;
        $result = TestDataMapperSoftDeletion::purge($entity);

        $this->assertSame(true, $result);
    }

    /**
     * Test 'purge' method, if entity is not deleted
     *
     * @expectedException Exception
     * @expectedExceptionMessageRegExp /Won't purge: \w+ isn't deleted/
     */
    public function testPurgeNotDeleted()
    {
        $query = ['_id' => 'a', '_deleted' => true];

        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('count')->with($query)->willReturn(0);

        $entity = $this->createPartialMock(TestEntityMeta::class, ['getId']);
        $entity->method('getId')->willReturn('a');

        TestDataMapperSoftDeletion::$collectionMock = $collection;
        TestDataMapperSoftDeletion::purge($entity);
    }

    /**
     * Test 'purgeAll' method
     */
    public function testPurgeAll()
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('deleteMany')->with(['_deleted' => true])->willReturn(true);

        TestDataMapperSoftDeletion::$collectionMock = $collection;
        $result = TestDataMapperSoftDeletion::purgeAll();

        $this->assertSame(true, $result);
    }
}
