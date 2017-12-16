<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Mongo\Document\BasicImplementation,
    Jasny\DB\Entity\Identifiable,
    Jasny\DB\Dataset\Sorted;

/**
 * Stub for lazy document instance
 */
class TestDocumentBasicEmptyFilter implements Identifiable, Sorted
{
    use BasicImplementation;

    /**
     * Get the field to sort on
     *
     * @return string|array
     */
    public static function getDefaultSorting()
    {

    }

    /**
     * Convert ID to a filter
     * Test case when for some reason null is returned
     *
     * @param string|array $id  ID or filter
     * @return array
     */
    protected static function idToFilter($id)
    {
        return null;
    }
}
