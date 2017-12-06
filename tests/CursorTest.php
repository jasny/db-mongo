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
     * Test 'getNext' method
     */
    public function testGetNext()
    {
        $cursor = $this->createPartialMock(Cursor::class, ['next', 'current']);
        $cursor->expects($this->once())->method('next');
        $cursor->expects($this->once())->method('current')->willReturn('test');

        $result = $cursor->getNext();

        $this->assertEquals('test', $result);
    }
}
