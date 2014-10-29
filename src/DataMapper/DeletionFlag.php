<?php

namespace Jasny\DB\Mongo\DataMapper;

use Jasny\DB\Entity,
    Jasny\DB\Mongo\Common;

/**
 * Implementation of soft deletion using a flag (for documents).
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait DeletionFlag
{
    use Common\DeletionFlag;
    
    /**
     * Check if document is flagged as deleted
     * 
     * @param Entity $document
     * @return boolean
     */
    public static function isDeleted(Entity $document)
    {
        if (isset($document->_deleted)) return $document->_deleted;
        return static::getCollection()->count(['_id' => $document->_id, '_deleted' => true]) > 0;
    }
    
    /**
     * Delete the document
     * 
     * @param Entity $document
     */
    public function delete(Entity $document)
    {
        static::getCollection()->update(['_id' => $document->_id], ['$set' => ['_deleted' => true]]);
        if (isset($document->_deleted)) $document->_deleted = true;
    }
    
    /**
     * Undelete the document
     * 
     * @param Entity $document
     */
    public function undelete(Entity $document)
    {
        static::getCollection()->update(['_id' => $document->_id], ['$set' => ['_deleted' => false]]);
        if (isset($document->_deleted)) $document->_deleted = false;
    }
    
    /**
     * Purge deleted document
     * 
     * @return $this
     * @throws Exception if document isn't deleted
     */
    public function purge(Entity $document)
    {
        if (!static::isDeleted($document)) throw new Exception("Won't purge: Document isn't deleted");
        static::getCollection()->remove(['_id' => $this->_id, '_deleted' => true]);
        
        return $this;
    }
}
