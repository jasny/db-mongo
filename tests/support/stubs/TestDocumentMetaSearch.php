<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Mongo\Document\BasicImplementation,
    Jasny\DB\Entity\Identifiable,
    Jasny\DB\Dataset\Sorted,
    Jasny\DB\Mongo\Dataset\Search\PoormansImplementation,
    Jasny\DB\Mongo\Document\MetaImplementation,
    Jasny\Meta\TypeCasting;

/**
 * Stub for document instance with meta and search
 */
class TestDocumentMetaSearch implements Identifiable, Sorted, TypeCasting
{
    use MetaImplementation,
        PoormansImplementation;

    /**
     * Fields for searching
     * @var array
     **/
    public static $searchFields;

    /**
     * @var string
     **/
    public static $collection;

    /**
     * Connection mock
     * @var Jasny\DB
     **/
    public static $connectionMock;

    /**
     * Collection mock
     * @var Jasny\DB\Mongo\Collection
     **/
    public static $collectionMock;

    /**
     * EntitySet stub
     * @var array
     **/
    public static $entitySetMock;

    /**
     * @var string
     **/
    public $id;

    /**
     * @searchField
     * @var string
     **/
    public $foo;

    /**
     * @var string
     **/
    public $bar;

    /**
     * @searchField
     * @var string
     **/
    public $zoo;

    /**
     * Hidden var
     * @var string
     **/
    protected $potato;

    /**
     * Get the database connection mock
     *
     * @return Jasny\DB
     */
    protected static function getDB()
    {
        return static::$connectionMock;
    }

    /**
     * Get the Mongo collection mock
     *
     * @return \Jasny\DB\Mongo\Collection
     */
    protected static function getCollection()
    {
        return static::$collectionMock;
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
        if (is_callable($opts['checkArgs'])) {
            $func = $opts['checkArgs'];
            $func($id);
        }

        return !$opts['fooReturn']; //Return params to check if they were passed correctly
    }

    /**
     * Get the field to sort on
     *
     * @return string|array
     */
    public static function getDefaultSorting()
    {
        return ['id'];
    }

    /**
     * Stub entity set
     *
     * @param Entities[]|\Traversable $entities  Array of entities
     * @param int|\Closure            $total     Total number of entities (if set is limited)
     * @param int                     $flags     Control the behaviour of the entity set
     * @param mixed                   ...        Additional are passed to the constructor
     * @return
     */
    public static function entitySet($entities = [], $total = null, $flags = 0)
    {
        if (is_callable($total)) {
            call_user_func($total);
        }

        return static::$entitySetMock;
    }

    /**
     * Cast properties
     *
     * @return $this
     */
    public function cast()
    {

    }

    /**
     * Cast to string
     *
     * @return string
     */
    public function __toString()
    {
        return 'document: ' . $this->id;
    }
}
