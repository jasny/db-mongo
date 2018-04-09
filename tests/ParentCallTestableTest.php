<?php

namespace Jasny\DB\Mongo;

/**
 * @covers Jasny\DB\Mongo\ParentCallTestable
 */
class ParentCallTestableTest extends TestHelper
{
    /**
     * Test 'parent' method
     */
    public function testParent()
    {
        $item = new TestableChild();
        $result = $item->foo('child', 'called');

        $this->assertSame('child middle base called', $result);
    }
}
