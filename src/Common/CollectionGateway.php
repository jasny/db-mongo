<?php

namespace Jasny\DB\Mongo\Common;

use Jasny\DB\Mongo\DB;

/**
 * Static methods to interact with a collection (as both document and data mapper)
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait CollectionGateway
{
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
     * Get the document class
     * 
     * @return string
     */
    protected static function getDocumentClass()
    {
        return get_called_class();
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
        
        return static::getDB()->selectCollection($name , static::getDocumentClass());
    }
    
    
    /**
     * Return filter defaults
     * 
     * @return array
     */
    protected static function getFilterDefaults()
    {
        return [];
    }
    
    /**
     * Convert ID to a filter
     * 
     * @param string|array $id  ID or filter
     * @return array
     */
    protected static function idToFilter($id)
    {
        if ($id instanceof \Jasny\DB\Entity && isset($id->_id) && $id->_id instanceof \MongoId)
            return ['_id' => $id->_id];
        
        if ($id instanceof \MongoId) return ['_id' => $id];

        if (is_object($id) || is_array($id)) return $id;
        
        if (is_string($id) && ctype_xdigit($id) && strlen($id) === 24) return ['_id' => new \MongoId($id)];
        
        trigger_error("Invalid MongoID '$id'", E_USER_NOTICE);
        return null;
    }
    
    /**
     * Fetch a document.
     * 
     * @param string|array $id  ID or filter
     * @return static
     */
    public static function fetch($id)
    {
        $filter = static::idToFilter($id) ;
        if (!isset($filter)) return null;

        $query = static::filterToQuery($filter);
        return static::getCollection()->findOne($query);
    }
    
    /**
     * Check if a document exists.
     * 
     * @param string|array $id  ID or filter
     * @return boolean
     */
    public static function exists($id)
    {
        $filter = static::idToFilter($id);
        if (!isset($filter)) return null;
        
        $query = static::filterToQuery($filter);
        return (boolean)static::getCollection()->count($query, 1);
    }
    
    /**
     * Fetch all documents.
     * 
     * @param array     $filter
     * @param array     $sort
     * @param int|array $limit  Limit or [limit, offset]
     * @return static[]
     */
    public static function fetchAll(array $filter = [], $sort = [], $limit = null)
    {
        $query = static::filterToQuery($filter);
        
        if (is_a(get_called_class(), 'Jasny\DB\Mongo\Sorted', true)) {
            $sort += static::getDefaultSorting();
        }
        
        list($limit, $offset) = (array)$limit + [null, null];
        
        $cursor = static::getCollection()->find($query, [], $sort, $limit, $offset);
        return array_values(iterator_to_array($cursor));
    }

    /**
     * Fetch all descriptions.
     * 
     * @param array     $filter
     * @param array     $sort
     * @param int|array $limit  Limit or [limit, offset]
     * @return array
     */
    public static function fetchList(array $filter = [], $sort = [], $limit = null)
    {
        $list = [];
        foreach (static::fetchAll($filter, $sort, $limit) as $record) {
            $list[$record->getId()] = (string)$record;
        }
        
        return $list;
    }

    /**
     * Count all documents in the collection
     * 
     * @param array $filter
     * @return int
     */
    public static function count(array $filter = [])
    {
        $query = static::filterToQuery($filter);
        return static::getCollection()->count($query);
    }
    
    
    /**
     * Convert a Jasny DB styled filter to a MongoDB query.
     * 
     * @param array $filter
     * @return array
     */
    protected static function filterToQuery($filter)
    {
        $filter += static::getFilterDefaults();
        
        if (is_a(get_called_class(), 'Jasny\DB\FieldMapping', true)) {
            $filter = static::mapToFields($filter);
        }

        $query = DB::filterToQuery($filter);
        
        return $query;
    }
}
