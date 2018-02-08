<?php

namespace Jasny\DB\Mongo\Document;

use Jasny\DB\Mongo\TestHelper,
    Jasny\DB\Mongo\TestDocumentSoftDeletion,
    Jasny\DB\Mongo\Collection;

/**
 * @covers Jasny\DB\Mongo\Document\SoftDeletion\FlagImplementation
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
            [['deleted' => false], ['foo' => 'bar', '_deleted' => null]],
            [['deleted' => 'only'], ['foo' => 'bar', '_deleted' => true]],
            [['from-trash'], ['foo' => 'bar', '_deleted' => true]],
            [['deleted' => 'included'], ['foo' => 'bar']],
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
        $document = $this->createPartialMock(TestDocumentSoftDeletion::class, []);

        $result = $this->callProtectedMethod($document, 'filterToQuery', [$filter, $options]);

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
            ['foo', 1, true],
            ['foo', 0, false],
            [null, 1, false],
            [null, 0, false]
        ];
    }

    /**
     * Test 'isDeleted' method
     *
     * @dataProvider isDeletedProvider
     */
    public function testIsDeleted($id, $count, $expected)
    {
        $query = ['_id' => $id, '_deleted' => true];

        $document = $this->createPartialMock(TestDocumentSoftDeletion::class, []);
        $document->id = $id;

        $collection = $this->initCollection();

        $collection->expects($this->any())->method('count')->with($query)->willReturn($count);
        $result = $document->isDeleted();
        $this->assertSame($expected, $result);
    }

    /**
     * Test 'delete' method
     */
    public function testDelete()
    {
        $id = 'foo';
        $query = ['_id' => $id, '_deleted' => null];

        $document = $this->createPartialMock(TestDocumentSoftDeletion::class, []);
        $document->id = $id;

        $collection = $this->initCollection();
        $collection->expects($this->once())->method('update')->with($query, ['$set' => ['_deleted' => true]]);

        $result = $document->delete();

        $this->assertSame($document, $result);
    }

    /**
     * Test 'undelete' method
     */
    public function testUndelete()
    {
        $id = 'foo';
        $query = ['_id' => $id, '_deleted' => true];

        $document = $this->createPartialMock(TestDocumentSoftDeletion::class, []);
        $document->id = $id;

        $collection = $this->initCollection();
        $collection->expects($this->once())->method('update')->with($query, ['$unset' => ['_deleted' => 1]]);

        $result = $document->undelete();

        $this->assertSame($document, $result);
    }

    /**
     * Test 'purge' method
     */
    public function testPurge()
    {
        $id = 'foo';
        $query = ['_id' => $id, '_deleted' => true];

        $document = $this->createPartialMock(TestDocumentSoftDeletion::class, ['isDeleted']);
        $document->expects($this->once())->method('isDeleted')->willReturn(true);
        $document->id = $id;

        $collection = $this->initCollection();
        $collection->expects($this->once())->method('remove')->with($query);

        $result = $document->purge();

        $this->assertSame($document, $result);
    }

    /**
     * Test 'purge' method for non-deleted document
     *
     * @expectedException Exception
     * @expectedExceptionMessageRegExp /Won't purge: \S+ isn't deleted/
     */
    public function testPurgeException()
    {
        $document = $this->createPartialMock(TestDocumentSoftDeletion::class, ['isDeleted']);
        $document->expects($this->once())->method('isDeleted')->willReturn(false);

        $collection = $this->initCollection();
        $collection->expects($this->never())->method('remove');

        $document->purge();
    }

    /**
     * Test 'purgeAll' method
     */
    public function testPurgeAll()
    {
        $collection = $this->initCollection();
        $collection->expects($this->once())->method('remove')->with(['_deleted' => true]);

        TestDocumentSoftDeletion::purgeAll();
    }

    /**
     * Setup collection mock
     *
     * @return Collection
     */
    protected function initCollection()
    {
        $collection = $this->createMock(Collection::class);
        TestDocumentSoftDeletion::$collectionMock = $collection;

        return $collection;
    }
}
