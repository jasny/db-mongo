<?php

namespace Jasny\DB\Mongo\Dataset\Sorted;

/**
 * Prepare the document to be sorted by casting it to a string
 *
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait Implementation
{
    /**
     * Get the field to sort on
     *
     * @return array
     */
    public static function getDefaultSorting()
    {
        return ['_sort'];
    }

    /**
     * Prepare sorting field
     *
     * @return array
     */
    protected function prepareDataForSort()
    {
        $sort = strtolower(iconv("UTF-8", "ASCII//TRANSLIT", (string)$this));
        return ['_sort' => $sort];
    }
}
