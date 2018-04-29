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
class DB extends \MongoDB\Database implements Connection, Connection\Namable
{
    use Connection\Namable\Implemention;

    const ASCENDING = 1;
    const DESCENDING = -1;

    /**
     * @param \MongoDB\Driver\Manager|string|array $client  Manager or settings
     * @param string                                        $name
     * @param array $options
     * @codeCoverageIgnore
     */
    public function __construct($manager, $name = null, $options = [])
    {
        if (is_array($manager) || $manager instanceof \stdClass) {
            $client = $this->createManagerFromOptions($manager);
            $manager = $client->getManager();
        } else if (is_string($manager)) {
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
    private function createClientFromOptions($options)
    {
        $options = (array)$options;
        $server = $options['client'];
        $parts = explode('/', $server);
        $dbName = isset($options['database']) ? $options['database'] : null;

        if (count($parts) < 4 && $dbName) {
            $server .= '/' . $dbName;
        }

        unset($options['client'], $options['database']);

        return new Client($server, $options);
    }

    /**
     * Gets a collection
     *
     * @param string $name
     * @param array $options
     * @return Mongo\DB\Collection
     */
    public function selectCollection($name, $options = [])
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
     * Check if value is a MongoDB specific type
     *
     * @param mixed $value
     * @return boolean
     */
    protected static function isMongoType($value)
    {
        return
            $value instanceof BSON\Binary ||
            $value instanceof BSON\UTCDateTime ||
            $value instanceof BSON\Decimal128 ||
            $value instanceof BSON\ObjectId ||
            $value instanceof BSON\MaxKey ||
            $value instanceof BSON\MinKey ||
            $value instanceof BSON\Regex ||
            $value instanceof BSON\Timestamp;
    }


    /**
     * Convert property to mongo type.
     * Works recursively for objects and arrays.
     *
     * @param mixed   $value
     * @param boolean $escapeKeys  Escape '.' and '$'
     * @return mixed
     */
    public static function toMongoType($value, $escapeKeys = false)
    {
        // return true;

        if (static::isMongoType($value)) {
            return $value;
        }

        if ($value instanceof \DateTime) {
            return new BSON\UTCDateTime($value->getTimestamp() * 1000);
        }

        if ($value instanceof Blob) {
            return new BSON\Binary($value, BSON\Binary::TYPE_GENERIC);
        }

        if ($value instanceof Entity\Identifiable) {
            $data = $value->toData();
            return isset($data['_id']) ? $data['_id'] : $value->getId();
        }

        if ($value instanceof Entity) {
            $value = $value->toData();
        }

        if ($value instanceof \ArrayObject || $value instanceof EntitySet) {
            $value = $value->getArrayCopy();
        }

        if (is_object($value) && !$value instanceof \stdClass) {
            throw new \MongoDB\Exception\InvalidArgumentException("Don't know how to cast a " . get_class($value) . " object to a mongo type");
        }

        if (is_array($value) || $value instanceof \stdClass) {
            $copy = [];

            foreach ($value as $k => $v) {
                $key = $escapeKeys ? strtr($k, ['\\' => '\\\\', '$' => '\\u0024', '.' => '\\u002e']) : $k;
                $copy[$key] = static::toMongoType($v, $escapeKeys); // Recursion
            }

            $value = is_object($value) ? (object)$copy : $copy;
        }

        return $value;
    }

    /**
     * Convert mongo type to value
     *
     * @param mixed   $value
     * @param boolean $translateKeys
     * @return mixed
     */
    public static function fromMongoType($value)
    {
        if (is_array($value) || $value instanceof \stdClass) {
            $out = [];

            foreach ($value as $k => $v) {
                // Unescape special characters in keys
                $unescape = strpos($k, '\\\\') !== false || strpos($k, '\\u') !== false;
                $key = $unescape ? json_decode('"' . addcslashes($k, '"') . '"') : $k;

                $out[$key] = self::fromMongoType($v); // Recursion
            }

            $isNumeric = is_array($value) &&
                (key($value) === 0 && array_keys($value) === array_keys(array_fill(0, count($value), null))) ||
                !count($value);

            return !$isNumeric ? (object)$out : $out;
        }

        if ($value instanceof BSON\UTCDateTime) {
            return $value->toDateTime();
        }

        if ($value instanceof BSON\Binary) {
            return new Blob($value->getData());
        }

        return $value;
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

        foreach ($filter as $key => $filterVal) {
            if ($key[0] === '$') {
                throw new \Exception("Invalid filter key '$key'. Starting with '$' isn't allowed.");
            }

            list($field, $operator) = array_map('trim', explode('(', str_replace(')', '', $key))) + [1 => null];
            $value = static::toMongoType($filterVal);

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
