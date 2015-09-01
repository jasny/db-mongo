<?php

namespace Jasny\DB\Mongo\Document\SoftDeletion;

/**
 * Implementation of soft deletion using a flag (for documents).
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait DeletionFlag
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
     * @return boolean
     */
    public function isDeleted()
    {
        $filter = static::idToFilter($this);
        $query = static::filterToQuery($filter, ['from-trash']);
        
        if (empty($query['_id'])) return false;
        return static::getCollection()->count($query) > 0;
    }
    
    /**
     * Delete the document
     * 
     * @return $this
     */
    public function delete()
    {
        $filter = static::idToFilter($this);
        $query = static::filterToQuery($filter);
        
        static::getCollection()->update($query, ['$set' => ['_deleted' => true]]);
        return $this;
    }
    
    /**
     * Undelete the document
     * 
     * @return $this
     */
    public function undelete()
    {
        $filter = static::idToFilter($this);
        $query = static::filterToQuery($filter, ['from-trash']);
        
        static::getCollection()->update($query, ['$unset' => ['_deleted' => 1]]);
        return $this;
    }
    
    /**
     * Purge a deleted document
     * 
     * @return $this
     */
    public function purge()
    {
        if (!$this->isDeleted()) throw new \Exception("Won't purge: " . get_called_class() . " isn't deleted");

        $filter = static::idToFilter($this);
        $query = static::filterToQuery($filter, ['from-trash']);
        
        static::getCollection()->remove($query);
        return $this;
    }
    
    /**
     * Purge all deleted documents
     */
    public static function purgeAll()
    {
        static::getCollection()->remove(['_deleted' => true]);
    }
}
