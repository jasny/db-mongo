<?php

namespace Jasny\DB\Mongo\Document;

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
    public function getDefaultSortField()
    {
        return '_sort';
    }
    
    /**
     * Prepare sorting field
     */
    protected function prepareSort()
    {
        $this->_sort = strtolower(iconv("UTF-8", "ASCII//TRANSLIT", (string)$this));
    }
}
