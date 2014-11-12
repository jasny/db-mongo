<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Entity,
    Jasny\DB\Mongo,
    Jasny\Meta\Introspection,
    Jasny\Meta\TypedObject,
    Jasny\DB\FieldMapping,
    Jasny\DB\FieldMap;

/**
 * Full implementation for Mongo documents as Active Record
 */
abstract class Document implements
    Mongo\Document,
    Entity\LazyLoading,
    Mongo\Sorted,
    Introspection,
    TypedObject,
    FieldMapping
{
    use Mongo\Document\Basics,
        Mongo\Document\LazyLoading,
        Mongo\Document\AutoSorting,
        FieldMap,
        Entity\Meta
    {
        Mongo\Document\Basics::setValues as private _setValues;
        Mongo\Document\Basics::save as private _save;
        Mongo\Document\Basics::jsonSerialize as private _jsonSerialize;
        Mongo\Document\LazyLoading::lazyload as private _lazyload;
    }
    
    /**
     * Class constructor
     */
    public function __construct()
    {
        if (!$this->_id instanceof \MongoId) $this->_id = new \MongoId($this->_id);
        $this->cast();
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
     * Create a ghost object.
     * 
     * @param mixed|array $values  Unique ID or values
     * @return static
     */
    public static function lazyload($values)
    {
        return static::_lazyload($values)->cast();
    }
    
    /**
     * Set the values.
     * 
     * @param array|object $values
     * @return $this
     */
    public function setValues($values)
    {
        if (isset($values['id'])) $values['_id'] = $values['id'];
        
        return $this->_setValues($values)->cast();
    }
    
    /**
     * Save the document
     * 
     * @return $this
     */
    public function save()
    {
        $this->cast();
        return $this->_save();
    }

    
    /**
     * Check no other document with the same value of the property exists
     * 
     * @param type $property
     * @return boolean
     */
    public function hasUnique($property)
    {
        if (!isset($this->$property)) return true;
        return !static::exists(['_id !=' => $this->_id, $property => $this->$property]);
    }

    /**
     * Prepare object for json
     * 
     * @return object
     */
    public function jsonSerialize()
    {
        $object = (object)(['id' => null] + (array)$this->_jsonSerialize());
        $object->id = $object->_id;
        unset($object->_id);
        
        return $object;
    }
    
    /**
     * Clear property for each child.
     * 
     * @param array        $list
     * @param string|array $prop
     */
    protected function jsonSerializeUnsetIn(&$list, $prop)
    {
        foreach ($list as &$item) {
            $item = $item instanceof \JsonSerializable ? $item->jsonSerialize() : clone $item;
            
            foreach ((array)$prop as $p) {
                if (isset($item->$p)) unset($item->$p);
            }
        }
    }
}
