<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Blob,
    Jasny\DB\Entity,
    Jasny\DB\Entity\Identifiable,
    Jasny\DB\EntitySet,
    MongoDB\Client,
    MongoDB\Driver\Manager,
    MongoDB\BSON;

/**
 * @covers Jasny\DB\Mongo\DB
 */
class DBTest extends TestHelper
{
    /**
     * Provide data for testing 'createClientFromOptions' method
     *
     * @return array
     */
    public function createClientFromOptionsProvider()
    {
        return [
            [['client' => 'mongodb://test-host'], 'mongodb://test-host'],
            [['client' => 'mongodb://test-host', 'database' => 'test-db'], 'mongodb://test-host/test-db'],
            [(object)['client' => 'mongodb://test-host', 'database' => 'test-db'], 'mongodb://test-host/test-db'],
            [(object)['client' => 'mongodb://test-host/test-db'], 'mongodb://test-host/test-db'],
            [(object)['client' => 'mongodb://test-host/test-db', 'database' => 'another-db'], 'mongodb://test-host/test-db'],
        ];
    }

    /**
     * Test '__construct' method
     *
     * @dataProvider createClientFromOptionsProvider
     */
    public function testCreateClientFromOptions($options, $expectedUri)
    {
        $mock = $this->getMockBuilder(DB::class)
            ->disableOriginalConstructor()
            ->getMock();

        $result = $this->callProtectedMethod($mock, 'createClientFromOptions', [$options]);
        $this->assertInstanceOf(Client::class, $result);

        $uri = $this->getPrivatePropery($result, 'uri');
        $this->assertSame($expectedUri, $uri);
    }

    /**
     * Test 'selectCollection' method
     */
    public function testSelectCollection()
    {
        $collectionName = 'test-collection';
        $dbName = 'test-db';

        $manager = new Manager('mongodb://test-host'); //Can not be mocked, as it's final
        $db = $this->getMockBuilder(DB::class)
            ->disableOriginalConstructor()
            ->setMethods(['getManager', 'getDatabaseName'])
            ->getMock();

        $db->expects($this->once())->method('getManager')->willReturn($manager);
        $db->expects($this->once())->method('getDatabaseName')->willReturn($dbName);

        $result = $db->selectCollection($collectionName);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame($dbName, $result->getDatabaseName());
        $this->assertSame($collectionName, $result->getCollectionName());
        $this->assertSame($manager, $result->getManager());
    }

    /**
     * Test getting collection using magic '__get()' method
     */
    public function testGetCollection()
    {
        $name = 'foo_collection';

        $db = $this->getMockBuilder(DB::class)
            ->disableOriginalConstructor()
            ->setMethods(['selectCollection'])
            ->getMock();

        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $db->expects($this->once())->method('selectCollection')->with($name)->willReturn($collection);
        $result = $db->$name;

        $this->assertEquals($collection, $result);
    }

    /**
     * Test 'filterToQuery' method, if filter key starts with '$'
     *
     * @expectedException Exception
     * @expectedExceptionMessage Invalid filter key '$foo'. Starting with '$' isn't allowed.
     */
    public function testFilterToQueryExceptionDollar()
    {
        DB::filterToQuery(['$foo' => 'bar']);
    }

    /**
     * Test 'filterToQuery' method, if operator is invalid
     *
     * @expectedException Exception
     * @expectedExceptionMessage Invalid filter key 'foo(bar)'. Unknown operator 'bar'.
     */
    public function testFilterToQueryExceptionOperator()
    {
        DB::filterToQuery(['foo(bar)' => 'baz']);
    }

    /**
     * Test 'filterToQuery' method
     */
    public function testFilterToQuery()
    {
        $entity = $this->createMock(Entity::class);
        $entity->expects($this->once())->method('toData')->willReturn(['en1' => 'val1', 'en2' => 'val2']);

        $filter = [
            'foo' => 'bar',
            'a(not)' => 'bar2',
            'b(min)' => 'some',
            'c(max)' => 'pum',
            'baz(any)' => $entity,
            'zoo(none)' => 'crum',
            'pipe(all)' => 'prum'
        ];

        $expected = [
            'foo' => 'bar',
            'a' => ['$ne' => 'bar2'],
            'b' => ['$gte' => 'some'],
            'c' => ['$lte' => 'pum'],
            'baz' => ['$in' => ['en1' => 'val1', 'en2' => 'val2']],
            'zoo' => ['$nin' => 'crum'],
            'pipe' => ['$all' => 'prum']
        ];

        $result = DB::filterToQuery($filter);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test 'sortToQuery' method
     */
    public function testSortToQuery()
    {
        $sort = ['foo', '^bar'];
        $expected = ['foo' => DB::ASCENDING, 'bar' => DB::DESCENDING];

        $result = DB::sortToQuery($sort);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test 'sortToQuery' method with wrong field value
     *
     * @expectedException Exception
     * @expectedExceptionMessage Invalid sort key '$foo'. Starting with '$' isn't allowed.
     */
    public function testSortToQueryException()
    {
        DB::sortToQuery(['$foo']);
    }

    /**
     * Create mock with disabled constructor
     *
     * @param string $class
     * @return object
     */
    protected function createMockNoConstructor($class)
    {
        return $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
