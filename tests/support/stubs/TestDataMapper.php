<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\DataMapper,
    Jasny\DB\FieldMapping,
    Jasny\DB\Mongo\DataMapper\Implementation as DataMapperImplementation;

/**
 * Concrete implementation of Data mapper
 */
class TestDataMapper implements DataMapper, FieldMapping
{
    use DataMapperImplementation;

    /**
     * Collection mock
     * @var \Jasny\DB\Mongo\Collection
     **/
    public static $collectionMock;

    /**
     * Entity class
     * @var string
     **/
    public static $entityClass;

    /**
     * @var DateTime
     **/
    public $date;

    /**
     * @var string
     **/
    public $zoo;

    /**
     * Get the Mongo collection.
     *
     * @return \Jasny\DB\Mongo\Collection
     */
    protected static function getCollection()
    {
        return static::$collectionMock;
    }
}
