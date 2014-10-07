<?php

namespace Jasny\DB\Mongo;

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
     * Fetch a document.
     * 
     * @param string|array $filter  ID or filter
     * @return static
     */
    public static function fetch($filter)
    {
        if ($filter instanceof \MongoId) $query = ['_id' => $query];
        if (is_string($filter)) $filter = ['_id' => new \MongoId($query)];

        $filter['_deleted'] = false;
        return parent::fetch($filter);
    }
    
    /**
     * Check if a document exists.
     * 
     * @param string|array $filter  ID or filter
     * @return boolean
     */
    public static function exists($filter)
    {
        if ($filter instanceof \MongoId) $query = ['_id' => $query];
        if (is_string($filter)) $filter = ['_id' => new \MongoId($query)];
        
        $filter['_deleted'] = false;
        return parent::exists($filter);
    }
    
    /**
     * Fetch all documents.
     * 
     * @param array $filter
     * @param array $sort
     * @return static[]
     */
    public static function fetchAll(array $filter = [], $sort = null)
    {
        $filter['_deleted'] = false;
        return parent::fetchAll($filter, $sort);
    }
    
    /**
     * Fetch all deleted documents.
     * 
     * @param array $filter
     * @param array $sort
     * @return static[]
     */
    public static function fetchDeleted(array $filter = [], $sort = null)
    {
        $filter['_deleted'] = true;
        return parent::fetchAll($filter, $sort);
    }

    /**
     * Count all documents in the collection
     * 
     * @param array $filter
     */
    public static function count(array $filter)
    {
        $filter['_deleted'] = false;
        return parent::count($filter, $sort);
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
        return parent::count($filter, $sort);
    }
    
    /**
     * Purge all deleted documents
     */
    public static function purgeAll()
    {
        parent::getCollection()->remove(['_deleted' => true]);
    }
}
