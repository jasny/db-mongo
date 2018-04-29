<?php

namespace Jasny\DB\Mongo\TypeCast;

use Jasny\DB\Blob,
    Jasny\DB\Entity,
    Jasny\DB\Entity\Identifiable,
    Jasny\DB\EntitySet,
    Jasny\DB\Mongo\TestHelper,
    MongoDB\BSON;

/**
 * @covers Jasny\DB\Mongo\TypeCast\DeepCast
 */
class DeepCastTest extends TestHelper
{
    /**
     * Provide data for testing 'toMongoType' method
     *
     * @return array
     */
    public function toMongoTypeProvider()
    {
        //Mongo data types are final classes, so we can not mock them
        $values = [
            new BSON\Binary('data', BSON\Binary::TYPE_GENERIC),
            new BSON\UTCDateTime(),
            new BSON\Decimal128(1),
            new BSON\ObjectId(),
            new BSON\MaxKey(),
            new BSON\MinKey(),
            new BSON\Regex('foo'),
            new BSON\Timestamp(1, 1)
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
     * Test 'toMongoType' method with arguments of mongo classes
     *
     * @dataProvider toMongoTypeProvider
     * @param Closure $value
     */
    public function testToMongoType($value)
    {
        $typeCast = $this->createPartialMock(DeepCast::class, []);

        $result = $typeCast->toMongoType($value());
        $this->assertEquals($value(), $result);
    }

    /**
     * Test 'toMongoType' method with DateTime argument
     */
    public function testToMongoTypeDateTime()
    {
        $date = $this->createMock(\DateTime::class);
        $date->method('getTimestamp')->willReturn(123);

        $typeCast = $this->createPartialMock(DeepCast::class, []);

        $result = $typeCast->toMongoType($date);
        $this->assertInstanceOf(BSON\UTCDateTime::class, $result);

        $asDate = $result->toDateTime();
        $this->assertEquals($date->getTimestamp(), $asDate->getTimestamp());
    }

    /**
     * Test 'toMongoType' method with Blob argument
     */
    public function testToMongoTypeBlob()
    {
        $blob = $this->createPartialMock(Blob::class, []);
        $this->setPrivateProperty($blob, 'data', 'Some data');

        $typeCast = $this->createPartialMock(DeepCast::class, []);

        $result = $typeCast->toMongoType($blob);

        $this->assertInstanceOf(BSON\Binary::class, $result);
        $this->assertEquals('Some data', (string)$result);
    }

    /**
     * Test 'toMongoType' method with Identifiable argument
     */
    public function testToMongoTypeIdentifiable()
    {
        $entity = $this->createMock(Identifiable::class);
        $entity->expects($this->once())->method('toData')->willReturn(['_id' => 'foo']);

        $typeCast = $this->createPartialMock(DeepCast::class, []);

        $result = $typeCast->toMongoType($entity);
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

        $typeCast = $this->createPartialMock(DeepCast::class, []);

        $result = $typeCast->toMongoType($entity);
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

        $typeCast = $this->createPartialMock(DeepCast::class, []);

        $result = $typeCast->toMongoType($value, $escapeKeys);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test 'toMongoType' method with object, that can not be cast
     *
     * @expectedException MongoDB\Exception\InvalidArgumentException
     */
    public function testToMongoTypeWrongClass()
    {
        $value = $this->createMock(TestHelper::class);
        $typeCast = $this->createPartialMock(DeepCast::class, []);

        $result = $typeCast->toMongoType($value);
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

        $typeCast = $this->createPartialMock(DeepCast::class, []);

        $result = $typeCast->toMongoType($value, true);

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
            new BSON\Decimal128(1),
            new BSON\ObjectId(),
            new BSON\MaxKey(),
            new BSON\MinKey(),
            new BSON\Regex('foo'),
            new BSON\Timestamp(1, 1)
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
            [$func($values[5])]
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
        $typeCast = $this->createPartialMock(DeepCast::class, []);

        $result = $typeCast->fromMongoType($value());
        $this->assertEquals($value(), $result);
    }

    /**
     * Test 'fromMongoType' method with MongoDB\BSON\UTCDateTime argument
     */
    public function testFromMongoTypeDate()
    {
        $value = new BSON\UTCDateTime(123000);
        $typeCast = $this->createPartialMock(DeepCast::class, []);

        $result = $typeCast->fromMongoType($value);

        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertEquals(123, $result->getTimestamp());
    }

    /**
     * Test 'fromMongoType' method with MongoDB\BSON\Binary argument
     */
    public function testFromMongoTypeBlob()
    {
        $value = new BSON\Binary('Some data', BSON\Binary::TYPE_GENERIC);
        $typeCast = $this->createPartialMock(DeepCast::class, []);

        $result = $typeCast->fromMongoType($value);

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

        $typeCast = $this->createPartialMock(DeepCast::class, []);

        $result = $typeCast->fromMongoType($value);

        $this->assertEquals($expected, $result);
    }
}
