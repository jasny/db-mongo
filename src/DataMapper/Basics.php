<?php

namespace Jasny\DB\Mongo\DataMapper;

use Jasny\DB\Entity,
    Jasny\DB\Mongo\Common;

/**
 * Basics for a Mongo DataMapper
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait Basics
{
    use Common\CollectionGateway;
    
    /**
     * Get class name for the documents this mapper maps to.
     * 
     * @return string
     */
    protected static function getDocumentClass()
    {
        if (isset(static::$documentClass)) return static::$documentClass;
        
        if (substr(get_called_class(), -6) !== 'Mapper') throw new Exception("Unable to determine document class");
        return substr(get_called_class(), 0, -6);
    }
    
    
    /**
     * Get the data that needs to be saved in the DB
     * 
     * @param Entity $document
     * @return array
     */
    protected function toData(Entity $document)
    {
        $values = $document->getValues();
        if ($this instanceof \Jasny\DB\FieldMapping) $values = static::mapToFields($values);
        
        return $values;
    }
    
    /**
     * Save the document
     * 
     * @param Entity $document
     */
    public function save(Entity $document)
    {
        if ($document instanceof LazyLoading && $document->isGhost()) throw new \Exception("Unable to save: This " .
            get_class($document) . " entity isn't fully loaded. First expand, than edit, than save.");
        
        if (!$document->_id instanceof \MongoId) $document->_id = new \MongoId($this->_id);
        if ($this instanceof Sorted && method_exists($this, 'prepareSort')) $this->prepareSort();
        
        static::getCollection()->save($document);
    }
    
    /**
     * Delete the document
     * 
     * @param Entity $document
     */
    public function delete($document)
    {
        static::getCollection()->remove(['_id' => $document->_id]);
    }
}
