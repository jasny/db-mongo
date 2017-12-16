<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Mongo\Document\BasicImplementation,
    Jasny\DB\Entity\Identifiable,
    Jasny\DB\Dataset\Sorted;

/**
 * Stub for lazy document instance
 */
class TestDocumentBasic implements Identifiable, Sorted
{
    use BasicImplementation;

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
     * EntitySet stub
     * @var array
     **/
    public static $entitySetMock;

    /**
     * @var string
     **/
    public $id;

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
     * Cast to string
     *
     * @return string
     */
    public function __toString()
    {
        return 'document: ' . $this->id;
    }
}
