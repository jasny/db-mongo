<?php

namespace Jasny\DB\Mongo\Dataset;

use Jasny\DB\Mongo\TestDocumentBasic,
    Jasny\DB\Mongo\TestDocumentBasicEmptyFilter,
    Jasny\DB\Mongo\TestHelper,
    Jasny\DB\Mongo\TypeCast,
    Jasny\DB\Mongo\Collection,
    Jasny\DB\Mongo\DB,
    Jasny\DB\Mongo\TestDocumentLazy,
    Jasny\DB\Mongo\Cursor;

/**
 * @covers Jasny\DB\Mongo\Dataset\Implementation
 */
class ImplementationTest extends TestHelper
{
    /**
     * Test 'getDocumentClass' method
     */
    public function testGetDocumentClass()
    {
        $document = new TestDocumentBasic();

        $result = $this->callProtectedMethod($document, 'getDocumentClass', []);
        $this->assertEquals(TestDocumentBasic::class, $result);
    }

    /**
     * Provide data for testing 'getCollectionName' method
     *
     * @return array
     */
    public function getCollectionNameProvider()
    {
        return [
            ['test_collection', 'test_collection'],
            [null, 'test_document_basics']
        ];
    }

    /**
     * Test 'getCollectionName' method
     *
     * @dataProvider getCollectionNameProvider
     */
    public function testGetCollectionName($collection, $expected)
    {
        TestDocumentBasic::$collection = $collection;

        $document = new TestDocumentBasic();
        $result = $this->callProtectedMethod($document, 'getCollectionName', []);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test 'getCollection' method
     */
    public function testGetCollection()
    {
        TestDocumentBasic::$collection = 'test_collection';

        $db = $this->createMock(DB::class);
        $db->expects($this->once())->method('selectCollection')->with('test_collection', ['documentClass' => TestDocumentBasic::class])->willReturn('foo_result');

        TestDocumentBasic::$connectionMock = $db;
        $document = new TestDocumentBasic();

        $result = $this->callProtectedMethod($document, 'getCollection', []);

        $this->assertEquals('foo_result', $result);
    }

    /**
     * Test 'castForDB' method
     */
    public function testCastForDB()
    {
        $data = ['foo' => 'bar'];

        $document = new TestDocumentBasic();
        $result = $this->callProtectedMethod($document, 'castForDB', [$data]);

        $this->assertSame($data, $result);
    }

    /**
     * Test 'idToFilter' method with id as array
     */
    public function testIdToFilterArray()
    {
        $id = ['foo'];

        $document = new TestDocumentBasic();
        $result = $this->callProtectedMethod($document, 'idToFilter', [$id]);

        $this->assertEquals($id, $result);
    }

    /**
     * Test 'idToFilter' method with id as document
     */
    public function testIdToFilterDocument()
    {
        $document = new TestDocumentBasic();
        $document->id = 'foo';

        $result = $this->callProtectedMethod($document, 'idToFilter', [$document]);

        $this->assertEquals(['id' => 'foo'], $result);
    }

    /**
     * Test 'idToFilter' method with id as scalar
     */
    public function testIdToFilterScalar()
    {
        $id = 3;

        $document = new TestDocumentBasic();
        $result = $this->callProtectedMethod($document, 'idToFilter', [$id]);

        $this->assertEquals(['id' => 3], $result);
    }

    /**
     * Test 'idToFilter' method with id as mongo id
     */
    public function testIdToFilterMongo()
    {
        $id = new \MongoDB\BSON\ObjectId();

        $document = new TestDocumentBasic();
        $result = $this->callProtectedMethod($document, 'idToFilter', [$id]);

        $this->assertSame(['id' => $id], $result);
    }

    /**
     * Test 'idToFilter' method with id as unallowed object
     *
     * @expectedException Exception
     * @expectedExceptionMessageRegExp /A Mock_DateTime_\S+ can't be used as a filter/
     */
    public function testIdToFilterWrongTypeObject()
    {
        $id = $this->createMock(\DateTime::class);

        $document = new TestDocumentBasic();
        $result = $this->callProtectedMethod($document, 'idToFilter', [$id]);
    }

    /**
     * Test 'idToFilter' method with id as unallowed type
     *
     * @expectedException Exception
     * @expectedExceptionMessageRegExp /A NULL can't be used as a filter/
     */
    public function testIdToFilterWrongType()
    {
        $document = new TestDocumentBasic();
        $result = $this->callProtectedMethod($document, 'idToFilter', [null]);
    }

    /**
     * Test 'idToFilter' method with id as mongo type, for class that is not identifiable
     *
     * @expectedException Exception
     * @expectedExceptionMessageRegExp /Unable to query using a object: \S+\\TestDocumentLazy isn't identifiable/
     */
    public function testIdToFilterNotIdentifiableMongo()
    {
        $id = new \MongoDB\BSON\ObjectId();

        $document = new TestDocumentLazy();
        $result = $this->callProtectedMethod($document, 'idToFilter', [$id]);
    }

    /**
     * Test 'idToFilter' method with id as scalar, for class that is not identifiable
     *
     * @expectedException Exception
     * @expectedExceptionMessageRegExp /Unable to query using a integer: \S+\\TestDocumentLazy isn't identifiable/
     */
    public function testIdToFilterNotIdentifiableScalar()
    {
        $id = 3;

        $document = new TestDocumentLazy();
        $result = $this->callProtectedMethod($document, 'idToFilter', [$id]);
    }

    /**
     * Test 'filterToQuery' method
     */
    public function testFilterToQuery()
    {
        $filter = ['foo' => 'bar', 'zoo(not)' => 'boo'];
        $expected = ['foo' => 'bar', 'zoo' => ['$ne' => ['boo']]];

        $document = new TestDocumentBasic();
        $result = $this->callProtectedMethod($document, 'filterToQuery', [$filter]);
    }

    /**
     * Test 'fetch' method
     */
    public function testFetch()
    {
        $id = ['foo' => 'bar'];
        $query = $id;

        $collection = $this->initCollection();
        $collection->expects($this->once())->method('findOne')->with($query)->willReturn('foo_result');

        $result = TestDocumentBasic::fetch($id);

        $this->assertEquals('foo_result', $result);
    }

    /**
     * Test 'exists' method
     */
    public function testExists()
    {
        $id = ['foo' => 'bar'];
        $query = $id;

        $collection = $this->initCollection();
        $collection->expects($this->once())->method('count')->with($query)->willReturn(1);

        $result = TestDocumentBasic::exists($id);

        $this->assertSame(true, $result);
    }

    /**
     * Test 'fetch' method when filter is null
     */
    public function testFetchNullFilter()
    {
        $result = TestDocumentBasicEmptyFilter::fetch(['foo' => 'bar']);

        $this->assertSame(null, $result);
    }

    /**
     * Test 'exists' method when filter is null
     */
    public function testExistsNullFilter()
    {
        $result = TestDocumentBasicEmptyFilter::exists(['foo' => 'bar']);

        $this->assertSame(null, $result);
    }

    /**
     * Provide data for testing 'fetchAll' method
     *
     * @return array
     */
    public function fetchAllProvider()
    {
        return [
            [
                ['^foo_field'],
                [3, 1],
                [
                    'sort' => ['foo_field' => DB::DESCENDING],
                    'limit' => 3,
                    'skip' => 1
                ]
            ],
            [
                [],
                3,
                [
                    'sort' => ['id' => DB::ASCENDING],
                    'limit' => 3
                ]
            ],
            [
                ['^id'],
                null,
                [
                    'sort' => ['id' => DB::DESCENDING]
                ]
            ]
        ];
    }

    /**
     * Test 'fetchAll' method
     *
     * @dataProvider fetchAllProvider
     */
    public function testFetchAll($sort, $limit, $findOpts)
    {
        $filter = ['foo' => 'bar'];
        $query = $filter;

        $cursor = $this->createMock(Cursor::class);

        $collection = $this->initCollection();
        $collection->expects($this->once())->method('find')->with($query, $findOpts)->willReturn($cursor);
        $collection->expects($this->once())->method('count')->with($query);

        $entitySet = $this->initEntitySet();

        $result = TestDocumentBasic::fetchAll($filter, $sort, $limit);

        $this->assertSame($entitySet, $result);
    }

    /**
     * Test 'fetchPairs' method
     *
     * @dataProvider fetchAllProvider
     */
    public function testFetchPairs($sort, $limit, $findOpts)
    {
        $filter = ['foo' => 'bar'];
        $query = $filter;

        $cursor = $this->createMock(Cursor::class);

        $collection = $this->initCollection();
        $collection->expects($this->once())->method('find')->with($query, $findOpts)->willReturn($cursor);
        $collection->expects($this->once())->method('count')->with($query);

        $entitySet = $this->initEntitySet();
        $entitySet[0]->id = 'a';
        $entitySet[1]->id = 'b';

        $result = TestDocumentBasic::fetchPairs($filter, $sort, $limit);
        $expected = [
            'a' => 'document: a',
            'b' => 'document: b'
        ];

        $this->assertSame($expected, $result);
    }

    /**
     * Test 'count' method
     */
    public function testCount()
    {
        $filter = ['foo' => 'bar'];
        $query = $filter;

        $collection = $this->initCollection();
        $collection->expects($this->once())->method('count')->with($query)->willReturn(2);

        $result = TestDocumentBasic::count($filter);
        $this->assertSame(2, $result);
    }

    /**
     * Mock collection
     *
     * @return Collection
     */
    protected function initCollection()
    {
        TestDocumentBasic::$collection = 'test_collection';

        $collection = $this->createMock(Collection::class);

        $db = $this->createMock(DB::class);
        $db->expects($this->once())->method('selectCollection')->with('test_collection',['documentClass' => TestDocumentBasic::class])->willReturn($collection);

        TestDocumentBasic::$connectionMock = $db;

        return $collection;
    }

    /**
     * Stub or mock EntitySet
     *
     * @return array
     */
    protected function initEntitySet()
    {
        TestDocumentBasic::$entitySetMock = [
            $this->createPartialMock(TestDocumentBasic::class, []),
            $this->createPartialMock(TestDocumentBasic::class, [])
        ];

        return TestDocumentBasic::$entitySetMock;
    }
}
