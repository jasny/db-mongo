<?php

namespace Jasny\DB\Mongo\Dataset\Search;

use Jasny\Meta\TypedObject,
    Jasny\DB\Mongo\Dataset;

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
        if (!is_a($class, TypedObject::class, true)) return [];

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
     * @param int       $total   OUTPUT: total number of records
     * @param array     $opts
     * @return User[]
     */
    public static function search($terms, $filter, $sort = null, $limit = null, &$total = null, array $opts = [])
    {
        $query = static::searchQuery($terms) + static::filterToQuery($filter, $opts);
        
        if (is_a(get_called_class(), Dataset\Sorted::class, true)) {
            $sort = (array)$sort + static::getDefaultSorting();
        }
        
        list($lmt, $offset) = (array)$limit + [null, null];
        
        $cursor = static::getCollection()->find($query, [], $sort, $lmt, $offset);
        if (func_num_args() >= 5) $total = static::getCollection()->count($query);
        
        return array_values(iterator_to_array($cursor));
    }
}
