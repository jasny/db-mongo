<?php

namespace Jasny\DB\Mongo;

/**
 * @covers Jasny\DB\Mongo\Cursor
 */
class CursorTest extends TestHelper
{
    /**
     * Test 'getCollection' method
     */
    public function testGetCollection()
    {
        $collection = $this->createMock(Collection::class);

        $cursor = $this->createPartialMock(Cursor::class, []);
        $this->setPrivateProperty($cursor, 'collection', $collection);

        $result = $cursor->getCollection();

        $this->assertEquals($collection, $result);
    }

    /**
     * Test 'getIterator' method
     */
    public function testGetIterator()
    {
        $driverCursor = [['foo' => 'bar'], ['baz' => 'zoo']];

        $collection = $this->createPartialMock(Collection::class, ['getDocumentClass', 'asDocument']);

        $cursor = $this->createPartialMock(Cursor::class, []);
        $this->setPrivateProperty($cursor, 'collection', $collection);
        $this->setPrivateProperty($cursor, 'lazy', true);
        $this->setPrivateProperty($cursor, 'source', $driverCursor);

        $collection->expects($this->once())->method('getDocumentClass')->willReturn('FooClass');
        $collection->expects($this->exactly(2))->method('asDocument')->will($this->returnValueMap([
            [['foo' => 'bar'], true, 'Document1'],
            [['baz' => 'zoo'], true, 'Document2']
        ]));

        $result = [];
        foreach ($cursor as $value) {
            $result[] = $value;
        }

        $this->assertSame(['Document1', 'Document2'], $result);
    }

    /**
     * Provide data for testing 'getIterator' method, if driver cursor is empty
     *
     * @return array
     */
    public function getIteratorEmptyProvider()
    {
        return [
            [null],
            [[]],
            [(object)[]]
        ];
    }

    /**
     * Test 'getIterator' method, if driver cursor is empty
     *
     * @dataProvider getIteratorEmptyProvider
     */
    public function testGetIteratorEmpty($driverCursor)
    {
        $collection = $this->createPartialMock(Collection::class, ['getDocumentClass', 'asDocument']);

        $cursor = $this->createPartialMock(Cursor::class, []);
        $this->setPrivateProperty($cursor, 'collection', $collection);
        $this->setPrivateProperty($cursor, 'lazy', true);
        $this->setPrivateProperty($cursor, 'source', $driverCursor);

        $collection->expects($this->once())->method('getDocumentClass')->willReturn('FooClass');
        $collection->expects($this->never())->method('asDocument');

        $result = [];
        foreach ($cursor as $value) {
            $result[] = $value;
        }

        $this->assertSame([], $result);
    }

    /**
     * Test '__call' method
     */
    public function testCall()
    {
        $driverCursor = (new \DateTime())->setTimestamp(123);
        $cursor = $this->createPartialMock(Cursor::class, []);
        $this->setPrivateProperty($cursor, 'source', $driverCursor);

        $result = $cursor->getTimestamp();
        $this->assertSame(123, $result);
    }

    /**
     * Test 'toArrayCast' method
     */
    public function testToArrayCast()
    {
        $driverCursor = [['foo' => 'bar'], ['baz' => 'zoo']];

        $collection = $this->createPartialMock(Collection::class, ['getDocumentClass', 'asDocument']);

        $cursor = $this->createPartialMock(Cursor::class, []);
        $this->setPrivateProperty($cursor, 'collection', $collection);
        $this->setPrivateProperty($cursor, 'lazy', true);
        $this->setPrivateProperty($cursor, 'source', $driverCursor);

        $collection->method('getDocumentClass')->willReturn('FooClass');
        $collection->expects($this->exactly(2))->method('asDocument')->will($this->returnValueMap([
            [['foo' => 'bar'], true, 'Document1'],
            [['baz' => 'zoo'], true, 'Document2']
        ]));

        $result = $cursor->toArrayCast();

        $this->assertSame(['Document1', 'Document2'], $result);
    }

    /**
     * Test 'toArrayCast' method, if no documentClass is set
     */
    public function testToArrayCastNoCast()
    {
        $expected = ['foo', 'bar'];

        $collection = $this->createPartialMock(Collection::class, ['getDocumentClass']);
        $driverCursor = $this->createMock(TestDriverCursor::class);

        $cursor = $this->createPartialMock(Cursor::class, []);
        $this->setPrivateProperty($cursor, 'collection', $collection);
        $this->setPrivateProperty($cursor, 'source', $driverCursor);

        $collection->expects($this->once())->method('getDocumentClass')->willReturn(null);
        $driverCursor->expects($this->once())->method('toArray')->willReturn($expected);

        $result = $cursor->toArrayCast();

        $this->assertEquals($expected, $result);
    }

    /**
     * Test '__call' method, if method does not exists
     *
     * @expectedException BadMethodCallException
     */
    public function testCallNoMethod()
    {
        $driverCursor = new \stdClass();
        $cursor = $this->createPartialMock(Cursor::class, []);
        $this->setPrivateProperty($cursor, 'source', $driverCursor);

        $result = $cursor->getTimestamp();
    }
}
