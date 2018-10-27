<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\TypeConversion;

use Jasny\DB\Mongo\TypeConversion\CastToPHP;
use Jasny\TestHelper;
use MongoDB\BSON;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\TypeConversion\CastToPHP
 */
class CastToPHPTest extends TestCase
{
    use TestHelper;

    public function nopProvider()
    {
        $object = (object)['foo' => 42];
        $date = new \DateTime('2000-01-01');

        return [
            [$object],
            [10],
            ['foo'],
            [['hello', 'world']],
            [$date]
        ];
    }

    /**
     * @dataProvider nopProvider
     */
    public function testNop($value)
    {
        $cast = new CastToPHP();
        $result = $cast($value);

        $this->assertEquals($value, $result);
    }

    public function testDateTime()
    {
        $cast = new CastToPHP();
        $result = $cast(new BSON\UTCDateTime(strtotime('2000-01-01') * 1000));

        $this->assertEquals(new \DateTimeImmutable('2000-01-01'), $result);
    }

    public function testObjectId()
    {
        $cast = new CastToPHP();
        $result = $cast(new BSON\ObjectId('507f1f77bcf86cd799439011'));

        $this->assertEquals('507f1f77bcf86cd799439011', $result);
    }

    public function testBinary()
    {
        $cast = new CastToPHP();
        $result = $cast(new BSON\Binary("abc\0def", BSON\Binary::TYPE_GENERIC));

        $this->assertEquals("abc\0def", $result);
    }


    public function testRecursionArray()
    {
        $value = [
            ['one', 'two', 'three'],
            (object)['foo' => 42, 'date' => new BSON\UTCDateTime(strtotime('2000-01-01') * 1000)]
        ];

        $cast = new CastToPHP();
        $result = $cast($value);

        $expected = [
            ['one', 'two', 'three'],
            (object)['foo' => 42, 'date' => new \DateTimeImmutable('2000-01-01')]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testRecursionObject()
    {
        $value = (object)[
            'a' => ['one', 'two', 'three'],
            'b' => (object)['foo' => 42, 'date' => new BSON\UTCDateTime(strtotime('2000-01-01') * 1000)]
        ];

        $cast = new CastToPHP();
        $result = $cast($value);

        $expected = (object)[
            'a' => ['one', 'two', 'three'],
            'b' => (object)['foo' => 42, 'date' => new \DateTimeImmutable('2000-01-01')]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testRecursionIterator()
    {
        $value = new \ArrayIterator([
            ['one', 'two', 'three'],
            (object)['foo' => 42, 'date' => new BSON\UTCDateTime(strtotime('2000-01-01') * 1000)]
        ]);

        $cast = new CastToPHP();
        $result = $cast($value);

        $this->assertInstanceOf(\Iterator::class, $result);
        $resultArr = iterator_to_array($result, true);

        $expected = [
            ['one', 'two', 'three'],
            (object)['foo' => 42, 'date' => new \DateTimeImmutable('2000-01-01')]
        ];

        $this->assertEquals($expected, $resultArr);
    }

    /**
     * @expectedException \OverflowException
     */
    public function testRecursionCircularReference()
    {
        $objectA = new \stdClass();
        $objectB = new \stdClass();

        $objectA->b = $objectB;
        $objectB->a = $objectA;

        $cast = new CastToPHP();
        $cast($objectA);
    }

    
    public function testWithPersistable()
    {
        $object = new class() implements \JsonSerializable {
            public static function __set_state(array $data)
            {
                $new = new static();
                $new->foo = 42;
                $new->color = 'blue';
                $new->date = new \DateTimeImmutable('2001-01-01');

                return $new;
            }

            public function jsonSerialize() {}
        };
        $className = get_class($object);

        $data = [
            'foo' => 42,
            'color' => 'blue',
            'date' => new BSON\UTCDateTime(strtotime('2000-01-01') * 1000)
        ];

        $cast = (new CastToPHP)->withPersistable(\JsonSerializable::class);
        $result = $cast((object)(['__pclass' => $className] + $data));

        $this->assertInstanceOf($className, $result);
        $this->assertAttributeEquals(42, 'foo', $result);
        $this->assertAttributeEquals('blue', 'color', $result);
        $this->assertAttributeEquals(new \DateTimeImmutable('2001-01-01'), 'date', $result);
    }

    public function testWithPersistableWithCallback()
    {
        $className = 'CastToPHP' . ucfirst(__FUNCTION__) . 'JsonSerializable';

        $object = $this->getMockBuilder(\JsonSerializable::class)
            ->setMockClassName($className)
            ->getMock();
        $object->foo = 42;
        $object->color = 'blue';
        $object->date = new \DateTimeImmutable('2001-01-01');

        $data = [
            'foo' => 42,
            'color' => 'blue',
            'date' => new BSON\UTCDateTime(strtotime('2000-01-01') * 1000)
        ];

        $convert = $this->createCallbackMock($this->once(), [$className, $data], $object);

        $cast = (new CastToPHP)->withPersistable(\JsonSerializable::class, $convert);
        $result = $cast((object)(['__pclass' => $className] + $data));

        $this->assertInstanceOf($className, $result);
        $this->assertAttributeEquals(42, 'foo', $result);
        $this->assertAttributeEquals('blue', 'color', $result);
        $this->assertAttributeEquals(new \DateTimeImmutable('2001-01-01'), 'date', $result);
    }

    public function testWithNonPersistable()
    {
        $className = 'CastToPHP' . ucfirst(__FUNCTION__) . 'JsonSerializable';
        $this->getMockBuilder(\JsonSerializable::class)
            ->setMockClassName($className)
            ->getMock();

        $cast = new CastToPHP();

        $data = (object)['__pclass' => $className, 'foo' => 42];
        $result = @$cast($data);

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertEquals($data, $result);

        $this->assertLastError(E_USER_WARNING, "Won't cast object to '$className': class not marked as persistable");
    }


    public function testWithBSON()
    {
        $regexp = new BSON\Regex('\d+');

        $callback = $this->createCallbackMock($this->once(), [$regexp], '/abc/');
        $cast = (new CastToPHP)->withBSON(BSON\Regex::class, $callback);

        $result = $cast($regexp);
        $this->assertEquals('/abc/', $result);
    }

    public function testWithBinary()
    {
        $binary = new BSON\Binary("abc\0xyz", 140);

        $callback = $this->createCallbackMock($this->once(), [$binary], 'ABC:XYZ');
        $cast = (new CastToPHP)->withBinary(140, $callback);

        $result = $cast($binary);
        $this->assertEquals('ABC:XYZ', $result);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testWithBSONInvalidClass()
    {
        (new CastToPHP)->withBSON(\DateTime::class, function() {});
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testWithBinaryInvalidType()
    {
        (new CastToPHP)->withBinary(1000, function() {});
    }

    public function testWithBSONUnknown()
    {
        $regexp = new BSON\Regex('\d+');

        $cast = new CastToPHP();

        $result = @$cast($regexp);
        $this->assertSame($regexp, $result);

        $this->assertLastError(E_USER_WARNING, "Unable to convert MongoDB\BSON\Regex object to PHP type");
    }
}
