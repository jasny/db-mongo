<?php

namespace Jasny\DB\Mongo\Common;

use Jasny\DB\Mongo\DB;

/**
 * Static methods to interact with a collection
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait CollectionGateway
{
    /**
     * Fetch a document.
     * 
     * @param string|array $filter  ID or filter
     * @return static
     */
    public static function fetch($filter)
    {
        if ($filter instanceof \MongoId) $filter = ['_id' => $filter];
        if (is_string($filter)) $filter = ['_id' => new \MongoId($filter)];
        
        $query = static::filterToQuery($filter);
        return static::getCollection()->findOne($query);
    }
    
    /**
     * Check if a document exists.
     * 
     * @param string|array $filter  ID or filter
     * @return boolean
     */
    public static function exists($filter)
    {
        if ($filter instanceof \MongoId) $filter = ['_id' => $filter];
        if (is_string($filter)) $filter = ['_id' => new \MongoId($filter)];
        
        $query = static::filterToQuery($filter);
        return (boolean)static::getCollection()->count($query, 1);
    }
    
    /**
     * Fetch all documents.
     * 
     * @param array $filter
     * @param array $sort
     * @return static[]
     */
    public static function fetchAll(array $filter = [], $sort = null)
    {
        $query = static::filterToQuery($filter);
        if (!isset($sort) && property_exists(get_called_class(), '_sort')) $sort = ['_sort' => DB::ASCENDING];
        
        $cursor = static::getCollection()->find($query, [], $sort);
        return array_values(iterator_to_array($cursor));
    }

    /**
     * Fetch all descriptions.
     * 
     * @param array $filter
     * @param array $sort
     * @return static[]
     */
    public static function fetchList(array $filter = [], $sort = null)
    {
        $list = [];
        foreach (static::fetchAll($filter, $sort) as $record) {
            $list[$record->getId()] = (string)$record;
        }
        
        return $list;
    }

    /**
     * Count all documents in the collection
     * 
     * @param array $filter
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
    public static function filterToQuery($filter)
    {
        $query = DB::filterToQuery($filter);
        return static::mapToFields($query);
    }
}
