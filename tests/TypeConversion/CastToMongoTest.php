<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\TypeConversion;

use Jasny\DB\Mongo\TypeConversion\CastToMongo;
use Jasny\TestHelper;
use MongoDB\BSON;
use OverflowException;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * @covers \Jasny\DB\Mongo\TypeConversion\CastToMongo
 */
class CastToMongoTest extends TestCase
{
    use TestHelper;

    public function nopProvider()
    {
        $id = new BSON\ObjectId();
        $object = (object)['foo' => 42];

        $serializable = new class implements BSON\Serializable {
            public function bsonSerialize() { }
        };

        return [
            [10],
            ['foo'],
            [['hello', 'world']],
            [$id],
            [$object],
            [$serializable],
        ];
    }

    /**
     * @dataProvider nopProvider
     */
    public function testNop($value)
    {
        $cast = new CastToMongo();
        $result = $cast($value);

        $this->assertEquals($value, $result);
    }

    public function testDateTime()
    {
        $date = new \DateTimeImmutable('2000-01-01');

        $cast = new CastToMongo();
        $result = $cast($date);

        $expected = new BSON\UTCDateTime(strtotime('2000-01-01') * 1000);
        $this->assertEquals($expected, $result);
    }


    public function testRecursionArray()
    {
        $value = [
            ['one', 'two', 'three'],
            (object)['foo' => 42, 'date' => new \DateTimeImmutable('2000-01-01')]
        ];

        $cast = new CastToMongo();
        $result = $cast($value);

        $expected = [
            ['one', 'two', 'three'],
            (object)['foo' => 42, 'date' => new BSON\UTCDateTime(strtotime('2000-01-01') * 1000)]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testRecursionObject()
    {
        $value = (object)[
            'a' => ['one', 'two', 'three'],
            'b' => (object)['foo' => 42, 'date' => new \DateTimeImmutable('2000-01-01')]
        ];

        $cast = new CastToMongo();
        $result = $cast($value);

        $expected = (object)[
            'a' => ['one', 'two', 'three'],
            'b' => (object)['foo' => 42, 'date' => new BSON\UTCDateTime(strtotime('2000-01-01') * 1000)]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testRecursionIterator()
    {
        $value = new \ArrayIterator([
            ['one', 'two', 'three'],
            (object)['foo' => 42, 'date' => new \DateTimeImmutable('2000-01-01')]
        ]);

        $cast = new CastToMongo();
        $result = $cast($value);

        $this->assertInstanceOf(\Iterator::class, $result);
        $resultArr = iterator_to_array($result, true);

        $expected = [
            ['one', 'two', 'three'],
            (object)['foo' => 42, 'date' => new BSON\UTCDateTime(strtotime('2000-01-01') * 1000)]
        ];

        $this->assertEquals($expected, $resultArr);
    }

    public function testRecursionCircularReference()
    {
        $this->expectException(OverflowException::class);

        $objectA = new \stdClass();
        $objectB = new \stdClass();

        $objectA->b = $objectB;
        $objectB->a = $objectA;

        $cast = new CastToMongo();
        $cast($objectA);
    }


    public function testWithPersistable()
    {
        $className = 'CastToMongo' . ucfirst(__FUNCTION__) . 'JsonSerializable';

        $object = $this->getMockBuilder(\JsonSerializable::class)
            ->setMockClassName($className)
            ->getMock();
        $object->foo = 42;
        $object->color = 'blue';
        $object->date = new \DateTimeImmutable('2000-01-01');

        $cast = (new CastToMongo)->withPersistable(\JsonSerializable::class);
        $result = $cast($object);

        $expected = [
            '__pclass' => $className,
            'foo' => 42,
            'color' => 'blue',
            'date' => new BSON\UTCDateTime(strtotime('2000-01-01') * 1000)
        ];

        $this->assertEquals($expected, $result);
    }

    public function testWithPersistableCallback()
    {
        $className = 'CastToMongo' . ucfirst(__FUNCTION__) . 'JsonSerializable';

        $object = $this->getMockBuilder(\JsonSerializable::class)
            ->setMockClassName($className)
            ->getMock();

        $convert = $this->createCallbackMock($this->once(), [$object], ['foo' => 42, 'color' => 'blue']);

        $cast = (new CastToMongo)->withPersistable(\JsonSerializable::class, $convert);
        $result = $cast($object);

        $this->assertEquals(['__pclass' => $className, 'foo' => 42, 'color' => 'blue'], $result);
    }


    public function testWithConversionObject()
    {
        $className = 'CastToMongo' . ucfirst(__FUNCTION__) . 'JsonSerializable';

        $object = $this->getMockBuilder(\JsonSerializable::class)
            ->setMockClassName($className)
            ->getMock();

        $convert = $this->createCallbackMock($this->once(), [$object], 'foo-bar');

        $cast = (new CastToMongo)->withConversion(\JsonSerializable::class, $convert);
        $result = $cast($object);

        $this->assertEquals('foo-bar', $result);
    }

    public function testWithConversionResource()
    {
        $resource = fopen('data://text/plain,a', 'r');

        $convert = $this->createCallbackMock($this->once(), [$resource], new \DateTimeImmutable('2000-01-01'));

        $cast = (new CastToMongo)->withConversion('stream resource', $convert);
        $result = $cast($resource);

        $expected = new BSON\UTCDateTime(strtotime('2000-01-01') * 1000);
        $this->assertEquals($expected, $result);
    }


    public function unexpectedValueProvider()
    {
        $className = ucfirst(__FUNCTION__) . 'Class';
        $object = $this->getMockBuilder(\JsonSerializable::class)
            ->setMockClassName($className)
            ->getMock();

        $resource = fopen('data://text/plain,a', 'r');

        $closed = fopen('data://text/plain,a', 'r');
        fclose($closed);

        return [
            [$object, 'instance of ' . $className],
            [$resource, 'stream resource'],
            [$closed, 'resource (closed)']
        ];
    }

    /**
     * @dataProvider unexpectedValueProvider
     */
    public function testUnexpectedValue($value, $type)
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Unable to cast $type to MongoDB type");

        $cast = new CastToMongo();
        $cast($value);
    }
}
