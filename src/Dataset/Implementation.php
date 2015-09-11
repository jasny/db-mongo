<?php

namespace Jasny\DB\Mongo\Dataset;

use Jasny\DB\Mongo\DB,
    Jasny\DB\Entity\Identifiable,
    Jasny\DB\Dataset\Sorted;

/**
 * Static methods to interact with a collection (as both document and data mapper)
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait Implementation
{
    /**
     * Get the database connection
     * 
     * @return \Jasny\DB
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
     * Get the Mongo collection name.
     * Uses the static `$collection` property if available, otherwise guesses based on class name.
     * 
     * @return string
     */
    protected static function getCollectionName()
    {
        if (isset(static::$collection)) {
            $name = static::$collection;
        } else {
            $class = preg_replace('/^.+\\\\/', '', static::getDocumentClass());
            $plural = Inflector::pluralize($class);
            $name = Inflector::tableize($plural);
        }
        
        return $name;
    }
    
    /**
     * Get the Mongo collection.
     * 
     * @return \Jasny\DB\Mongo\Collection
     */
    protected static function getCollection()
    {
        $name = static::getCollectionName();
        return static::getDB()->selectCollection($name , static::getDocumentClass());
    }
    
    
    /**
     * Cast data to use in DB
     * 
     * @param array $data
     * @return array
     */
    protected static function castForDB($data)
    {
        return $data;
    }
    
    /**
     * Convert ID to a filter
     * 
     * @param string|array $id  ID or filter
     * @return array
     */
    protected static function idToFilter($id)
    {
        if (is_array($id)) return $id;
        
        if (is_a($id, static::getDocumentClass()) && $id instanceof Identifiable) {
            return [$id::getIdProperty() => $id->getId()];
        }
        
        if ($id instanceof \MongoId || is_scalar($id)) {
            $class = static::getDocumentClass();
            
            if (!is_a($class, Identifiable::class, true)) {
                throw new \Exception("Unable to query using a " . gettype($id) . ": $class isn't identifiable");
            }
            
            return [$class::getIdProperty() => $id];
        }
        
        $type = is_object($id) ? get_class($id) : gettype($id);
        throw new \Exception("A $type can't be used as a filter");
    }
    
    /**
     * Convert a Jasny DB styled filter to a MongoDB query.
     * 
     * @param array $filter
     * @param array $opts
     * @return array
     */
    protected static function filterToQuery($filter, array $opts = [])
    {
        $castedFilter = static::castForDB($filter, $opts);
        $mappedFilter = static::mapToFields($castedFilter);
        
        return DB::filterToQuery($mappedFilter);
    }

    
    /**
     * Fetch a document.
     * 
     * @param string|array $id  ID or filter
     * @param array        $opts
     * @return static
     */
    public static function fetch($id, array $opts = [])
    {
        $filter = static::idToFilter($id);
        if (!isset($filter)) return null;

        $query = static::filterToQuery($filter, $opts);
        return static::getCollection()->findOne($query);
    }
    
    /**
     * Check if a document exists.
     * 
     * @param string|array $id  ID or filter
     * @param array        $opts
     * @return boolean
     */
    public static function exists($id, array $opts = [])
    {
        $filter = static::idToFilter($id);
        if (!isset($filter)) return null;
        
        $query = static::filterToQuery($filter, $opts);
        return (boolean)static::getCollection()->count($query, 1);
    }
    
    /**
     * Fetch all documents.
     * 
     * @param array     $filter
     * @param array     $sort
     * @param int|array $limit  Limit or [limit, offset]
     * @param array     $opts
     * @return static[]|\Jasny\DB\Entity[]
     */
    public static function fetchAll(array $filter = [], $sort = [], $limit = null, array $opts = [])
    {
        // Query
        $query = static::filterToQuery($filter, $opts);
        $cursor = static::getCollection()->find($query);
        
        // Sort
        if (is_a(get_called_class(), Sorted::class, true)) {
            $sort = (array)$sort + static::getDefaultSorting();
        }
        $querySort = DB::sortToQuery($sort);
        if (!empty($querySort)) $cursor->sort($querySort);
        
        // Limit / skip
        list($limit, $skip) = (array)$limit + [null, null];
        
        if (isset($limit)) $cursor->limit($limit);
        if (isset($skip)) $cursor->skip($skip);

        // Return
        return array_values(iterator_to_array($cursor));
    }

    /**
     * Fetch all descriptions.
     * 
     * @param array     $filter
     * @param array     $sort
     * @param int|array $limit  Limit or [limit, offset]
     * @param array     $opts
     * @return array
     */
    public static function fetchList(array $filter = [], $sort = [], $limit = null, array $opts = [])
    {
        $list = [];
        foreach (static::fetchAll($filter, $sort, $limit, $opts) as $record) {
            $list[$record->getId()] = (string)$record;
        }
        
        return $list;
    }

    /**
     * Count all documents in the collection
     * 
     * @param array $filter
     * @param array $opts
     * @return int
     */
    public static function count(array $filter = [], array $opts = [])
    {
        $query = static::filterToQuery($filter, $opts);
        return static::getCollection()->count($query);
    }
}
