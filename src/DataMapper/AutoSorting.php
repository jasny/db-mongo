<?php

namespace Jasny\DB\Mongo;

/**
 * Prepare the document to be sorted by casting it to a string
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait AutoSorting
{
    public $_sort;
    
    /**
     * Get the field to sort on
     * 
     * @return string
     */
    public static function getDefaultSortField()
    {
        return '_sort';
    }
    
    /**
     * Prepare sorting field
     * 
     * @param Jasny\DB\Entity $entity
     */
    protected static function prepareSort($entity)
    {
        $entity->_sort = strtolower(iconv("UTF-8", "ASCII//TRANSLIT", (string)$entity));
    }
}
