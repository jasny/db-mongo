<?php

namespace Jasny\DB\Mongo\DataMapper;

use Jasny\DB\Entity,
    Jasny\DB\FieldMapping,
    Jasny\DB\Mongo\Dataset;

/**
 * Basics for a Mongo DataMapper
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait BasicImplementation
{
    use Dataset\Implementation,
        FieldMapping\Implementation;
    
    /**
     * Get the field map.
     * 
     * @return array
     */
    protected static function getFieldMap()
    {
        return ['_id' => 'id'];
    }   
    
    /**
     * Get class name for the documents this mapper maps to.
     * 
     * @return string
     */
    protected static function getEntityClass()
    {
        if (isset(static::$entityClass)) return static::$entityClass;
        
        if (substr(get_called_class(), -6) === 'Mapper') {
            $class = substr(get_called_class(), 0, -6);
            if (is_a($class, Entity::class)) return $class;
        }
        
        throw new Exception("Unable to determine entity class");
    }
    
    
    /**
     * Get the data that needs to be saved in the DB
     * 
     * @param Entity $document
     * @return array
     */
    protected static function toData(Entity $document)
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
    public static function save(Entity $document)
    {
        if ($document instanceof LazyLoading && $document->isGhost()) {
            throw new \Exception("Unable to save: This " . get_class($document) . " entity isn't fully loaded. "
                . "First expand, than edit, than save.");
        }
                
        static::getCollection()->save($document);
    }
    
    /**
     * Delete the document
     * 
     * @param Entity $document
     */
    public static function delete($document)
    {
        if (!$document instanceof Entity\Identifiable) {
            throw new Exception("A " . get_class($document) . " isn't identifiable");
        }
        
        $filter = [$document->getIdProperty() => $document->getId()];
        
        
        static::getCollection()->remove();
    }
}
