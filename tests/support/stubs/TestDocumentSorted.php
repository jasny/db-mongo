<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Dataset\Sorted,
    Jasny\DB\Mongo\Dataset\Sorted\Implementation as SortedImplementation,
    Jasny\DB\Mongo\Document\BasicImplementation;

/**
 * Stub for sorted document instance
 */
class TestDocumentSorted implements Sorted
{
    use BasicImplementation,
        SortedImplementation;

    /**
     * Cast document to string
     *
     * @return string
     */
    public function __toString()
    {
        return 'foo';
    }
}
