<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Mongo\Document\MetaImplementation;

/**
 * Stub for document with meta implementation
 *
 * @db test_db
 * @dbCollection test_collection
 */
class TestDocumentMeta
{
    use MetaImplementation;

    /**
     * @var string
     **/
    public static $collection = 'other_test_collection';

    /**
     * @id
     * @var string
     **/
    public $idField;

    /**
     * @dbSkip
     * @var string
     **/
    public $foo;

    /**
     * @dbFieldType int
     * @dbFieldName bar_in_db
     * @var int
     **/
    public $bar;

    /**
     * @var string
     * @dbFieldName zoo
     **/
    public $zoo;

    /**
     * @var string
     **/
    public $carol;
}
