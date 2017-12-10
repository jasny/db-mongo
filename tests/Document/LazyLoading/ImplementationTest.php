<?php

namespace Jasny\DB\Mongo\Document;

use Jasny\DB\Mongo\TestEntityLazy,
    Jasny\DB\Mongo\TestEntityLazyComplexId,
    Jasny\DB\Mongo\TestEntityLazySimpleId,
    Jasny\DB\Mongo\TestHelper;

/**
 * @covers Jasny\DB\Mongo\Document\LazyLoading\Implementation
 */
class ImplementationTest extends TestHelper
{
    /**
     * Test 'lazyload' method
     */
    public function testLazyload()
    {
        $values = ['zoo' => 'bar'];
        $result = TestEntityLazy::lazyload($values);

        $this->assertInstanceOf(TestEntityLazy::class, $result);
        $this->assertEquals('bar', $result->zoo);
    }

    /**
     * Test 'lazyload' method for simple mongo id
     */
    public function test()
    {
        $id = $this->createMock(\MongoId::class);
        $result = TestEntityLazySimpleId::lazyload($id);

        $this->assertInstanceOf(TestEntityLazySimpleId::class, $result);
    }

    /**
     * Test 'lazyload' method, if trying to load non-identifiable entity by mongo id
     *
     * @expectedException Exception
     * @expectedExceptionMessageRegExp /Unable to lazy load a MongoId for \S+\\TestEntityLazy: Identity property not defined/
     */
    public function testLazyloadIdentifiableException()
    {
        $id = $this->createMock(\MongoId::class);

        $result = TestEntityLazy::lazyload($id);
    }

    /**
     * Test 'lazyload' method, if trying to load entity with complex id by mongo id
     *
     * @expectedException Exception
     * @expectedExceptionMessageRegExp /Unable to lazy load a MongoId for \S+\\TestEntityLazyComplexId: Class has a complex identity/
     */
    public function testLazyloadComplexIdException()
    {
        $id = $this->createMock(\MongoId::class);

        $result = TestEntityLazyComplexId::lazyload($id);
    }
}
