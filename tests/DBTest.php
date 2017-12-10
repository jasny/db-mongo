<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Blob,
    Jasny\DB\Entity,
    Jasny\DB\Entity\Identifiable,
    Jasny\DB\EntitySet;

/**
 * @covers Jasny\DB\Mongo\DB
 */
class DBTest extends TestHelper
{
    /**
     * Test 'getClient' method
     */
    public function testGetClient()
    {
        $client = $this->getMockBuilder(\MongoClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        $db = $this->createPartialMock(DB::class, []);

        $this->setPrivateProperty($db, 'mongoClient', $client);

        $result = $db->getClient();
        $this->assertEquals($client, $result);
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
     * Provide data for testing 'toMongoType' method
     *
     * @return array
     */
    public function toMongoTypeProvider()
    {
        $values = [
            $this->createMock(\MongoBinData::class),
            $this->createMock(\MongoDate::class),
            $this->createMock(\MongoDBRef::class),
            $this->createMock(\MongoInt32::class),
            $this->createMock(\MongoInt64::class),
            $this->createMock(\MongoId::class),
            $this->createMock(\MongoMaxKey::class),
            $this->createMock(\MongoMinKey::class),
            $this->createMock(\MongoRegex::class),
            $this->createMock(\MongoTimestamp::class)
        ];

        //Use closure as provided value, because data provider can not provide unclonable values (e.g. mongo types)
        $func = function($value) {
            return function() use ($value) {
                return $value;
            };
        };

        return [
            [$func($values[0])],
            [$func($values[1])],
            [$func($values[2])],
            [$func($values[3])],
            [$func($values[4])],
            [$func($values[5])],
            [$func($values[6])],
            [$func($values[7])],
            [$func($values[8])],
            [$func($values[9])]
        ];
    }

    /**
     * Test 'toMongoType' method with arguments of mongo classes
     *
     * @dataProvider toMongoTypeProvider
     * @param Closure $value
     */
    public function testToMongoType($value)
    {
        $result = DB::toMongoType($value());
        $this->assertEquals($value(), $result);
    }

    /**
     * Test 'toMongoType' method with DateTime argument
     */
    public function testToMongoTypeDateTime()
    {
        $date = $this->createMock(\DateTime::class);
        $date->expects($this->once())->method('getTimestamp')->willReturn(123);

        $result = DB::toMongoType($date);

        $this->assertInstanceOf(\MongoDate::class, $result);
        $this->assertEquals(123, $result->sec);
    }

    /**
     * Test 'toMongoType' method with Blob argument
     */
    public function testToMongoTypeBlob()
    {
        $blob = $this->createPartialMock(Blob::class, []);
        $this->setPrivateProperty($blob, 'data', 'Some data');

        $result = DB::toMongoType($blob);

        $this->assertInstanceOf(\MongoBinData::class, $result);
        $this->assertEquals('Some data', $result->bin);
    }

    /**
     * Test 'toMongoType' method with Identifiable argument
     */
    public function testToMongoTypeIdentifiable()
    {
        $entity = $this->createMock(Identifiable::class);
        $entity->expects($this->once())->method('toData')->willReturn(['_id' => 'foo']);

        $result = DB::toMongoType($entity);
        $this->assertEquals('foo', $result);
    }

    /**
     * Test 'toMongoType' method with Identifiable argument, if '$entity->toData()' does not hold an id
     */
    public function testToMongoTypeIdentifiableGetId()
    {
        $entity = $this->createMock(Identifiable::class);
        $entity->expects($this->once())->method('toData')->willReturn(['bar' => 'foo']);
        $entity->expects($this->once())->method('getId')->willReturn('alpha');

        $result = DB::toMongoType($entity);
        $this->assertEquals('alpha', $result);
    }

    /**
     * Provide data for testing 'toMongoType' method with argument, that results in array (or stdClass) of values
     *
     * @return array
     */
    public function toMongoTypeArrayProvider()
    {
        $data = ['$foo1' => 'bar1', '$foo1.foo2\\.foo3' => 'bar2'];
        $escaped = ['\\u0024foo1' => 'bar1', '\\u0024foo1\\u002efoo2\\\\\\u002efoo3' => 'bar2'];

        return [
            [Entity::class, 'toData', $data, false, $data],
            [Entity::class, 'toData', $data, true, $escaped],
            [Entity::class, 'toData', (object)$data, false, (object)$data],
            [Entity::class, 'toData', (object)$data, true, (object)$escaped],
            [EntitySet::class, 'getArrayCopy', $data, false, $data],
            [EntitySet::class, 'getArrayCopy', $data, true, $escaped],
            [EntitySet::class, 'getArrayCopy', (object)$data, false, (object)$data],
            [EntitySet::class, 'getArrayCopy', (object)$data, true, (object)$escaped],
            [\ArrayObject::class, 'getArrayCopy', $data, false, $data],
            [\ArrayObject::class, 'getArrayCopy', $data, true, $escaped],
            [\ArrayObject::class, 'getArrayCopy', (object)$data, false, (object)$data],
            [\ArrayObject::class, 'getArrayCopy', (object)$data, true, (object)$escaped]
        ];
    }

    /**
     * Test 'toMongoType' method with argument, that results in array (or stdClass) of values
     *
     * @dataProvider toMongoTypeArrayProvider
     * @param array|stdClass $data
     * @param boolean $escapeKeys
     * @param array|stdClass $expected
     */
    public function testToMongoTypeArray($class, $method, $data, $escapeKeys, $expected)
    {
        $value = $this->createMock($class);
        $value->expects($this->once())->method($method)->willReturn($data);

        $result = DB::toMongoType($value, $escapeKeys);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test 'toMongoType' method with object, that can not be cast
     *
     * @expectedException MongoException
     */
    public function testToMongoTypeWrongClass()
    {
        $value = $this->createMock(TestHelper::class);
        $result = DB::toMongoType($value);
    }

    /**
     * Test 'toMongoType' method with recursive conversion
     */
    public function testToMongoTypeRecursive()
    {
        $entity = $this->createMock(Entity::class);
        $entity->expects($this->once())->method('toData')->willReturn(['en1' => 'val1', 'en2' => 'val2']);

        $entity2 = $this->createMock(Entity::class);
        $entity2->expects($this->once())->method('toData')->willReturn((object)['test' => 'rest']);

        $set = $this->createMock(EntitySet::class);
        $set->expects($this->once())->method('getArrayCopy')->willReturn(['key' => $entity2]);

        $data = [
            'foo' => 'bar',
            'foo2' => $entity,
            'foo3' => [
                '$baz' => $set
            ]
        ];

        $value = $this->createMock(\ArrayObject::class);
        $value->expects($this->once())->method('getArrayCopy')->willReturn($data);

        $result = DB::toMongoType($value, true);

        $expected = [
            'foo' => 'bar',
            'foo2' => ['en1' => 'val1', 'en2' => 'val2'],
            'foo3' => [
                '\\u0024baz' => [
                    'key' => (object)['test' => 'rest']
                ]
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * Provide data for testing 'fromMongoType' method
     *
     * @return array
     */
    public function fromMongoTypeProvider()
    {
        $values = [
            $this->createMock(\MongoDBRef::class),
            $this->createMock(\MongoInt32::class),
            $this->createMock(\MongoInt64::class),
            $this->createMock(\MongoId::class),
            $this->createMock(\MongoMaxKey::class),
            $this->createMock(\MongoMinKey::class),
            $this->createMock(\MongoRegex::class),
            $this->createMock(\MongoTimestamp::class)
        ];

        //Use closure as provided value, because data provider can not provide unclonable values (e.g. mongo types)
        $func = function($value) {
            return function() use ($value) {
                return $value;
            };
        };

        return [
            [$func($values[0])],
            [$func($values[1])],
            [$func($values[2])],
            [$func($values[3])],
            [$func($values[4])],
            [$func($values[5])],
            [$func($values[6])],
            [$func($values[7])]
        ];
    }

    /**
     * Test 'fromMongoType' method with arguments of mongo classes
     *
     * @dataProvider fromMongoTypeProvider
     * @param Closure $value
     */
    public function testFromMongoType($value)
    {
        $result = DB::fromMongoType($value());
        $this->assertEquals($value(), $result);
    }

    /**
     * Test 'fromMongoType' method with \MongoDate argument
     */
    public function testFromMongoTypeDate()
    {
        $value = $this->createMock(\MongoDate::class);
        $this->setPrivateProperty($value, 'sec', 123);

        $result = DB::fromMongoType($value);

        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertEquals(123, $result->getTimestamp());
    }

    /**
     * Test 'fromMongoType' method with \MongoBinData argument
     */
    public function testFromMongoTypeBlob()
    {
        $value = $this->createMock(\MongoBinData::class);
        $this->setPrivateProperty($value, 'bin', 'Some data');

        $result = DB::fromMongoType($value);

        $this->assertInstanceOf(Blob::class, $result);
        $this->assertEquals('Some data', (string)$result);
    }

    /**
     * Test 'fromMongoType' method for recursive conversion
     */
    public function testFromMongoTypeRecursive()
    {
        $value = [
            'foo' => 'bar',
            'foo2' => (object)['en1' => 'val1', 'en2\\u002een3' => 'val2'],
            'foo3' => [
                '\\u0024baz' => [
                    'key' => ['test', 'rest']
                ]
            ],
            'foo4' => []
        ];

        $expected = (object)[
            'foo' => 'bar',
            'foo2' => (object)['en1' => 'val1', 'en2.en3' => 'val2'],
            'foo3' => (object)[
                '$baz' => (object)[
                    'key' => ['test', 'rest']
                ]
            ],
            'foo4' => []
        ];

        $result = DB::fromMongoType($value);

        $this->assertEquals($expected, $result);
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
