<?php

namespace Jasny\DB\Mongo;

use Jasny\Meta\Introspection,
    Jasny\Meta\Introspection\AnnotationsImplementation,
    Jasny\DB\Entity,
    Jasny\DB\Entity\Identifiable,
    Jasny\DB\Mongo\Document\BasicImplementation;

/**
 * Stub for entity, implementing meta introspection
 */
class TestEntityMetaMongo implements Introspection, Entity, Identifiable
{
    use BasicImplementation,
        AnnotationsImplementation;

    /**
     * @var string
     * @dbFieldType \MongoDB\BSON\ObjectId
     **/
    public $id;
}
