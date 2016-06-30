<?php

namespace Jasny\DB\Mongo\Dataset\Search;

use Jasny\Meta\TypeCasting;
use Jasny\DB\Mongo\DB;
use Jasny\DB\Dataset\Sorted;

/**
 * Poorman's search implementation
 */
trait PoormansImplementation
{
    /**
     * Get the properties to search on
     * 
     * @return array
     */
    protected static function getSearchFields()
    {
        if (isset(static::$searchFields)) return static::$searchFields;

        $class = static::getDocumentClass();
        if (!is_a($class, TypeCasting::class, true)) return [];

        $fields = [];
        foreach ($class::meta()->ofProperties() as $prop => $meta) {
            if (isset($meta['searchField'])) $fields[] = $prop;
        }
        
        return $fields;
    }
    
    /**
     * Get a search query
     * 
     * @param string $terms
     * @return array
     */
    protected static function searchQuery($terms)
    {
        $search = [];
        $words = array_filter(preg_split('/\W+/', $terms));
        
        if (empty($words)) return [];
        
        $searchFields = static::getSearchFields();
        if (empty($searchFields)) throw new \Exception("Unable to search: No search index fields defined");
        
        if (count($searchFields) == 1) {
            $field = $searchFields[0];
            
            foreach ($words as $i => $word) {
                $search[$i][$field] = new \MongoRegex('/' . $word . '/i');
            }
        } else {
            foreach ($words as $i => $word) {
                $fields = [];
                foreach ($searchFields as $j => $field) {
                    $fields[$j][$field] = new \MongoRegex('/' . $word . '/i');
                }
                $search[$i]['$or'] = $fields;
            }
        }
        
        return ['$and' => $search];
    }
    
    /**
     * Search entities.
     * 
     * @param string    $terms
     * @param array     $filter
     * @param array     $sort
     * @param int|array $limit
     * @param array     $opts
     * @return EntitySet|User[]
     */
    public static function search($terms, $filter, $sort = null, $limit = null, array $opts = [])
    {
        $collection = static::getCollection();
        
        // Query
        $query = static::searchQuery($terms) + static::filterToQuery((array)$filter, $opts);
        $cursor = $collection->find($query);

        $totalFn = function() use($collection, $query) {
            return $collection->count($query);
        };
        
        // Sort
        if (is_a(get_called_class(), Sorted::class, true)) {
            $sort = (array)$sort + static::getDefaultSorting();
        }
        if (isset($sort)) $querySort = DB::sortToQuery($sort);
        if (!empty($querySort)) $cursor->sort($querySort);
        
        // Limit / skip
        list($limit, $skip) = (array)$limit + [null, null];
        
        if (isset($limit)) $cursor->limit($limit);
        if (isset($skip)) $cursor->skip($skip);
        
        $class = self::getDocumentClass();
        return $class::entitySet($cursor, $totalFn);
    }
}
