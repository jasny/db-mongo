<?php

namespace Jasny\DB\Mongo\Dataset\Sorted;

use Jasny\DB\Mongo\Dataset\Sorted\Implementation as SortedImplementation,
    Jasny\DB\Mongo\TestHelper,
    Jasny\DB\Mongo\TestDocumentSorted;

/**
 * @covers Jasny\DB\Mongo\Dataset\Sorted\Implementation
 */
class ImplementationTest extends TestHelper
{
    /**
     * Test 'getDefaultSorting' method
     */
    public function testGetDefaultSorting()
    {
        $trait = $this->getMockForTrait(SortedImplementation::class);
        $class = get_class($trait);

        $result = $class::getDefaultSorting();

        $this->assertSame(['_sort'], $result);
    }

    /**
     * Test 'prepareDataForSort' method
     */
    public function testPrepareDataForSort()
    {
        $document = $this->createPartialMock(TestDocumentSorted::class, ['__toString']);
        $document->expects($this->once())->method('__toString')->willReturn('Foo in HERE');

        $result = $this->callProtectedMethod($document, 'prepareDataForSort', []);
        $this->assertSame(['_sort' => 'foo in here'], $result);
    }
}
