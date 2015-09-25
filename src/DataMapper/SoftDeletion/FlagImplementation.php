<?php

namespace Jasny\DB\Mongo\DataMapper\SoftDeletion;

use Jasny\DB\Entity;
use Jasny\DB\Mongo\DB;

/**
 * Implementation of soft deletion using a flag (for documents).
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait FlagImplementation
{
    /**
     * Convert a Jasny DB styled filter to a MongoDB query.
     * 
     * @param array $filter
     * @param array $opts
     * @return array
     */
    protected static function filterToQuery($filter, array $opts = [])
    {
        $filter = static::castForDB($filter);
        $filter = static::mapToFields($filter);
        
        if (in_array('from-trash', $opts)) {
            $filter['_deleted'] = true;
        } elseif (!in_array('include-trash', $opts)) {
            $filter['_deleted'] = null;
        }
        
        return DB::filterToQuery($filter);
    }
    
    
    /**
     * Check if document is flagged as deleted
     * 
     * @param Entity $document
     * @return boolean
     */
    public static function isDeleted(Entity $document)
    {
        $filter = static::idToFilter($document);
        $query = static::filterToQuery($filter, ['from-trash']);
        
        if (empty($query['_id'])) return false;
        return static::getCollection()->count($query) > 0;
    }
    
    /**
     * Delete the document
     * 
     * @param Entity $document
     */
    public static function delete(Entity $document)
    {
        $filter = static::idToFilter($document);
        $query = static::filterToQuery($filter);
        
        static::getCollection()->update($query, ['$set' => ['_deleted' => true]]);
    }
    
    /**
     * Undelete the document
     * 
     * @param Entity $document
     */
    public static function undelete(Entity $document)
    {
        $filter = static::idToFilter($document);
        $query = static::filterToQuery($filter, ['from-trash']);
        
        static::getCollection()->update($query, ['$unset' => ['_deleted' => 1]]);
        return $this;
    }
    
    /**
     * Purge deleted document
     */
    public static function purge(Entity $document)
    {
        if (!static::isDeleted($document)) {
            throw new \Exception("Won't purge: " . get_class($document) . " isn't deleted");
        }

        $filter = static::idToFilter($document);
        $query = static::filterToQuery($filter, ['from-trash']);
        
        static::getCollection()->remove($query);
    }
    
    /**
     * Purge all deleted documents
     */
    public static function purgeAll()
    {
        static::getCollection()->remove(['_deleted' => true]);
    }
}
