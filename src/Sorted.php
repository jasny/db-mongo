<?php

namespace Jasny\DB\Mongo;

/**
 * Always sort a resultset
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
interface Sorted
{
    /**
     * Get the field to sort on
     * 
     * @return string
     */
    public static function getDefaultSortField();
}
