<?php

namespace Jasny\DB\Mongo\Document;

/**
 * Implementation of soft deletion using a flag (for documents).
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait DeletionFlag
{
    use \Jasny\DB\Mongo\Common\DeletionFlag;
    
    /**
     * The document is deleted
     * @var boolean
     */
    public $_deleted = null;
    
    /**
     * Check if document is flagged as deleted
     * 
     * @return boolean
     */
    public function isDeleted()
    {
        return $this->_deleted;
    }
    
    /**
     * Delete the document
     * 
     * @return $this;
     */
    public function delete()
    {
        if (!$this->_deleted) {
            static::getCollection()->update(['_id' => $this->_id], ['$set' => ['_deleted' => true]]);
            $this->_deleted = true;
        }
        
        return $this;
    }
    
    /**
     * Undelete the document
     * 
     * @return $this
     */
    public function undelete()
    {
        if ($this->_deleted) {
            static::getCollection()->update(['_id' => $this->_id], ['$set' => ['_deleted' => false]]);
            $this->_deleted = false;
        }
        
        return $this;
    }
    
    /**
     * Purge deleted document
     * 
     * @return $this
     * @throws Exception if document isn't deleted
     */
    public function purge()
    {
        if (!$this->_deleted) throw new Exception("Won't purge: Document isn't deleted");
        static::getCollection()->remove(['_id' => $this->_id, '_deleted' => true]);
        
        return $this;
    }
}
