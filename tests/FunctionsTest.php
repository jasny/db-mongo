<?php

namespace Jasny\DB\Mongo;

class FunctionsTest extends TestHelper
{
    /**
     * Test 'get_object_public_properties' function
     */
    public function testGetObjectPublicProperties()
    {
        $object = new class() {
            public $foo = 'foo1';
            public $bar = 'bar1';
            protected $zoo = 'zoo1';
            private $baz = 'baz1';
        };

        $result = get_object_public_properties($object);
        $expected = ['foo' => 'foo1', 'bar' => 'bar1'];

        $this->assertSame($expected, $result);
    }
}
