<?php

namespace Jasny\DB\Mongo;

use \Jasny\DB\Entity, Jasny\DB\Recordset;

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
    Entity\LazyLoadable,
    Recordset
{
    use Entity\Basics,
        Entity\LazyLoading,
        Common\CollectionGateway {
            Entity\LazyLoading::ghost as createGhost;
    }

    
    /**
     * Indexes to create on the collection.
     * @var array
     */
    static protected $indexes;
    
    
    /**
     * Unique ObjectId
     * @var \MongoId
     */
    public $_id;
    
    
    /**
     * Get the Mongo collection
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
        
        $collection = DB::conn()->selectCollection($name , get_called_class());
        if (isset(static::$indexes)) $collection->createIndexes(static::$indexes);
        
        return $collection;
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
     * Save the document
     * 
     * @return $this
     */
    public function save()
    {
        static::getCollection()->save($this);
        return $this;
    }
    
    /**
     * Save the document
     * 
     * @return $this
     */
    public function delete()
    {
        static::getCollection()->delete($this);
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
     * Prepare result when casting object to JSON
     * 
     * @return object
     */
    public function jsonSerialize()
    {
        $values = $this->getValues();
        
        foreach ($values as &$value) {
            if ($value instanceof \DateTime) $value = $value->format(\DateTime::ISO8601);
            if ($value instanceof \MongoId) $value = (string)$value;
        }
        
        return (object)$values;
    }
}
