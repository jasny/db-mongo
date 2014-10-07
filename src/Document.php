<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Entity,
    Jasny\DB\Recordset,
    Jasny\DB\FieldMapping,
    Jasny\DB\FieldMap;

/**
 * Base class for a Mongo document.
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
abstract class Document implements
    Entity,
    Entity\Identifiable,
    Entity\ActiveRecord,
    Entity\LazyLoading,
    Recordset,
    FieldMapping
{
    use Entity\Basics,
        Entity\SimpleLazyLoading,
        FieldMap,
        Common\CollectionGateway {
            Entity\Basics::instantiate as protected instantiateEntity;
            Entity\SimpleLazyLoading::ghost as protected createGhost;
        }
    
    
    /**
     * Unique ObjectId
     * @var \MongoId
     */
    public $_id;
    
    
    /**
     * Get the database connection
     * 
     * @return DB
     */
    protected static function getDB()
    {
        return \Jasny\DB::conn();
    }
    
    /**
     * Get the Mongo collection.
     * Uses the static `$collection` property if available, otherwise guesses based on class name.
     * 
     * @return Collection
     */
    protected static function getCollection()
    {
        if (isset(static::$collection)) {
            $name = static::$collection;
        } else {
            $class = preg_replace('/^.+\\\\/', '', get_called_class());
            $name = strtolower(preg_replace('/(?<=[a-z])([A-Z])(?![A-Z])/', '_$1', $class)); // snake_case
        }
        
        return static::getDB()->selectCollection($name , get_called_class());
    }
    
    /**
     * Get the field map.
     * 
     * @return array
     */
    protected static function getFieldMap()
    {
        return [];
    }
    
    
    /**
     * Constructor
     */
    public function __construct()
    {
        if (!$this->_id instanceof \MongoId) $this->_id = new \MongoId($this->_id);
    }
    
    /**
     * Get document id.
     * For linking documents within Mongo, use the `_id` property directly.
     * 
     * @return string
     */
    public function getId()
    {
        return (string)$this->_id;
    }

    /**
     * Get the data that needs to be saved in the DB
     * 
     * @return array
     */
    protected function toData()
    {
        $values = static::mapToFields($this->getValues());
        
        if (property_exists(get_called_class(), '_sort')) {
            $values['_sort'] = strtolower(iconv("UTF-8", "ASCII//TRANSLIT", (string)$this));
        }
        
        return $values;
    }
    
    /**
     * Save the document
     * 
     * @return $this
     */
    public function save()
    {
        if ($this->isGhost()) throw new \Exception("Unable to save: This " . get_called_class() . " entity " .
            "isn't fully loaded. First expand, than edit, than save.");
        
        static::getCollection()->save($this->toData());
        return $this;
    }
    
    /**
     * Save the document
     * 
     * @return $this
     */
    public function delete()
    {
        static::getCollection()->delete($this->_id);
        return $this;
    }
    
    
    /**
     * Create a ghost object.
     * 
     * @param mixed|array $values  Unique ID or values
     * @return static
     */
    public static function ghost($values)
    {
        if (is_string($values)) $values = ['_id' => new \MongoId($values)];
        if ($values instanceof \MongoId) $values = ['_id' => $values];
        
        return self::createGhost($values);
    }
    
    /**
     * Convert loaded values to an entity
     * 
     * @param array $values
     */
    public static function instantiate($values)
    {
        $props = static::mapFromFields($values);
        return static::instantiateEntity($props);
    }
    
    /**
     * Prepare result when casting object to JSON
     * 
     * @return object
     */
    public function jsonSerialize()
    {
        $this->expand();
        
        $values = $this->getValues();
        
        foreach ($values as &$value) {
            if ($value instanceof \DateTime) $value = $value->format(\DateTime::ISO8601);
            if ($value instanceof \MongoId) $value = (string)$value;
        }
        
        unset($values['_sort']);
        return (object)$values;
    }
}
