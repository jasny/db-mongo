<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Connection,
    Jasny\DB\Entity,
    Jasny\DB\EntitySet,
    Jasny\DB\Blob,
    MongoDB\Client,
    MongoDB\Driver\Manager,
    MongoDB\BSON;

/**
 * Instances of this class are used to interact with a Mongo database.
 *
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.com/db-mongo
 */
class DB extends \MongoDB\Database
{
    const ASCENDING = 1;
    const DESCENDING = -1;

    /**
     * @param \MongoDB\Driver\Manager|string|array $client  Manager or settings
     * @param string                               $name    Database name
     * @param array                                $options
     * @codeCoverageIgnore
     */
    public function __construct($manager, $name, $options = [])
    {
        if (is_array($manager) || $manager instanceof \stdClass) {
            $manager = $this->getOptionsAsString($manager);
        }

        if (is_string($manager)) {
            if (!$name) {
                $name = $this->getDbNameFromUri($manager);
            }

            $manager = new Manager($manager);
        }

        parent::__construct($manager, $name, $options);
    }

    /**
     * Create an instance of mongo client from a list of options
     *
     * @param array|object $options
     * @return \MongoDB\Client
     */
    private function getOptionsAsString($options)
    {
        $options = (array)$options;
        $conn = $options['client'];
        $parts = explode('/', $conn);
        $dbName = isset($options['database']) ? $options['database'] : null;

        if (count($parts) < 4 && $dbName) {
            $conn .= '/' . $dbName;
        }

        return $conn;
    }

    /**
     * Get db name from options
     *
     * @param string $uri
     * @return string|null
     */
    private function getDbNameFromUri($uri)
    {
        $parts = parse_url($uri);
        $path = isset($parts['path']) ? $parts['path'] : '';
        $dbName = trim($path, '/');

        return $dbName ?: null;
    }

    /**
     * Gets a collection
     *
     * @param string $name
     * @param array $options
     * @return Mongo\DB\Collection
     */
    public function selectCollection($name, array $options = [])
    {
        return new Collection(
            $this->getManager(),
            $this->getDatabaseName(),
            $name,
            $options
        );
    }

    /**
     * Get's a collection
     *
     * @param string $name
     * @return Collection
     */
    public function __get($name)
    {
        return $this->selectCollection($name);
    }

    /**
     * Convert a Jasny DB styled filter to a MongoDB query.
     *
     * @param array $filter
     * @return array
     */
    public static function filterToQuery($filter)
    {
        $query = [];
        $typeCast = new TypeCast\DeepCast();

        foreach ($filter as $key => $filterVal) {
            if ($key[0] === '$') {
                throw new \Exception("Invalid filter key '$key'. Starting with '$' isn't allowed.");
            }

            list($field, $operator) = array_map('trim', explode('(', str_replace(')', '', $key))) + [1 => null];
            $value = $typeCast->toMongoType($filterVal);

            switch ($operator) {
                case '':     $query[$field] = $value; break;
                case 'not':  $query[$field] = ['$ne' => $value]; break;
                case 'min':  $query[$field] = ['$gte' => $value]; break;
                case 'max':  $query[$field] = ['$lte' => $value]; break;
                case 'any':  $query[$field] = ['$in' => $value]; break;
                case 'none': $query[$field] = ['$nin' => $value]; break;
                case 'all':  $query[$field] = ['$all' => $value]; break;

                default: throw new \Exception("Invalid filter key '$key'. Unknown operator '$operator'.");
            }
        }

        return $query;
    }

    /**
     * Convert a Jasny DB styled sort array to a MongoDB sort.
     *
     * @param array $sort
     * @return array
     */
    public static function sortToQuery($sort)
    {
        $query = [];

        foreach ($sort as $key) {
            $order = self::ASCENDING;

            if ($key[0] === '^') {
                $key = substr($key, 1);
                $order = self::DESCENDING;
            }

            if ($key[0] === '$') {
                throw new \Exception("Invalid sort key '$key'. Starting with '$' isn't allowed.");
            }

            $query[$key] = $order;
        }

        return $query;
    }
}
