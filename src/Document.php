<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Entity,
    Jasny\Meta\Introspection,
    Jasny\Meta\TypedObject,
    Jasny\DB\FieldMapping,
    Jasny\DB\FieldMap;

/**
 * Base class for Mongo Documents
 */
abstract class Document implements
    Document\ActiveRecord,
    Entity\LazyLoading,
    Sorted,
    Introspection,
    TypedObject,
    FieldMapping
{
    use Document\Basics,
        Document\LazyLoading,
        FieldMap,
        Entity\Meta
    {
        Document\Basics::setValues as private _setValues;
        Document\Basics::save as private _save;
        Document\Basics::jsonSerialize as private _jsonSerialize;
        Document\LazyLoading::lazyload as private _lazyload;
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
     * Get the field to sort on
     * 
     * @return string
     */
    public static function getDefaultSorting()
    {
        return property_exists(__CLASS__, '_sort') ? ['_sort' => DB::ASCENDING] : [];
    }
    
    /**
     * Prepare sorting field
     */
    protected function prepareSort()
    {
        if (property_exists($this, '_sort')) {
            $this->_sort = strtolower(iconv("UTF-8", "ASCII//TRANSLIT", (string)$this));
        }
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
