<?php

namespace Jasny\DB\Mongo\Common;

/**
 * Implementation of soft deletion and trash using a deletion flag (common methods).
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait DeletionFlag
{
    /**
     * Return filter defaults
     * 
     * @return array
     */
    protected static function getFilterDefaults()
    {
        return ['_deleted' => null];
    }
    
    /**
     * Fetch a deleted document.
     * 
     * @param string|\MongoID|array $id  ID or filter
     * @return static
     */
    public static function fetchDeleted($id)
    {
        $filter = static::idToFilter($id);
        $filter['_deleted'] = true;
        
        return static::fetch($filter);
    }
    
    /**
     * Fetch all deleted documents.
     * 
     * @param array $filter
     * @param array $sort
     * @return static[]
     */
    public static function fetchAllDeleted(array $filter = [], $sort = null)
    {
        $filter['_deleted'] = true;
        return static::fetchAll($filter, $sort);
    }

    /**
     * Count all deleted documents in the collection
     * 
     * @param array $filter
     * @return static[]
     */
    public static function countDeleted(array $filter = [])
    {
        $filter['_deleted'] = true;
        return parent::count($filter);
    }
    
    /**
     * Purge all deleted documents
     */
    public static function purgeAll()
    {
        parent::getCollection()->remove(['_deleted' => true]);
    }
}
