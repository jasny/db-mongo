<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\DataMapper, Jasny\DB\Recordset;

/**
 * Data Mapper for fetching and storing entities using Mongo.
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
abstract class DataMapper implements DataMapper, Recordset
{
    use Common\CollectionGateway;
    
    /**
     * Indexes to create on the collection.
     * @var array
     */
    static protected $indexes;
    
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
            $class = preg_replace('/^.+\\\\/', '', static::getDocumentClass());
            $name = strtolower(preg_replace('/(?<=[a-z])([A-Z])(?![A-Z])/', '_$1', $class)); // snake_case
        }
        
        $collection = DB::conn()->selectCollection($name , static::getDocumentClass());
        if (isset(static::$indexes)) $collection->createIndexes(static::$indexes);
        
        return $collection;
    }
    
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
     * Save the document
     * 
     * @param Entity $document
     */
    public function save(Entity $document)
    {
        static::getCollection()->save($document);
    }
    
    /**
     * Delete the document
     * 
     * @param Entity $document
     */
    public function delete($document)
    {
        static::getCollection()->delete($document);
    }
}
