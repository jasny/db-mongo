<?php

namespace Jasny\DB\Mongo\Document;

use Jasny\DB\FieldMapping,
    Jasny\DB\Mongo\Common,
    Jasny\DB\Entity;

/**
 * Static methods to interact with a collection (as document)
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait Basics
{
    use Entity\Basics,
        Common\CollectionGateway
    {
        Entity\Basics::fromData as protected entityFromData;
    }
    
    /**
     * Unique ObjectId
     * @var \MongoId
     */
    public $_id;
    
    
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
        $values = $this->getValues();
        if ($this instanceof FieldMapping) $values = static::mapToFields($values);
        
        return $values;
    }
    
    /**
     * Save the document
     * 
     * @return $this
     */
    public function save()
    {
        if ($this instanceof Entity\LazyLoading && $this->isGhost()) throw new \Exception("Unable to save: This " .
            get_called_class() . " entity isn't fully loaded. First expand, than edit, than save.");
        
        if (!$this->_id instanceof \MongoId) $this->_id = new \MongoId($this->_id);
        if ($this instanceof Sorted && method_exists($this, 'prepareSort')) $this->prepareSort();
        
        static::getCollection()->save($this->toData());
        return $this;
    }
    
    /**
     * Delete the document
     * 
     * @return $this
     */
    public function delete()
    {
        static::getCollection()->remove(['_id' => $this->_id]);
        return $this;
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
        
        return (object)$values;
    }
    
    /**
     * Convert loaded values to an entity
     * 
     * @param array $values
     * @return static
     */
    public static function fromData($values)
    {
        if (is_a(get_called_class(), 'Jasny\DB\FieldMapping', true)) $values = static::mapFromFields($values);
        return static::entityFromData($values);
    }
}
