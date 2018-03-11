<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Mongo\DataMapper\SoftDeletion,
    Jasny\DB\Mongo\DataMapper\Implementation as DataMapperImplementation,
    Jasny\DB\FieldMapping,
    Jasny\DB\Mongo\TestEntityMeta,
    Jasny\DB\Entity;

/**
 * Stub for data mapper with soft deletion
 */
class TestDataMapperSoftDeletion implements SoftDeletion, FieldMapping
{
    use DataMapperImplementation,
        SoftDeletion\FlagImplementation
    {
        SoftDeletion\FlagImplementation::filterToQuery insteadof DataMapperImplementation;
        SoftDeletion\FlagImplementation::delete insteadof DataMapperImplementation;
    }

    /**
     * Mock for collection
     * @var \Jasny\DB\Mongo\Collection
     **/
    public static $collectionMock;

    /**
     * @var string
     **/
    public $id;

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
     * Get the document class
     *
     * @return string
     */
    protected static function getDocumentClass()
    {
        return TestEntityMeta::class;
    }

    /**
     * Save the entity
     *
     * @param Entity $entity
     * @param array  $opts
     */
    public static function save(Entity $entity, array $opts = [])
    {

    }
}
