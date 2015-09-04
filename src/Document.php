<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Entity,
    Jasny\DB\FieldMapping;

/**
 * Base class for Mongo Documents
 */
abstract class Document implements
    Document\ActiveRecord,
    Entity\Meta,
    FieldMapping,
    Entity\LazyLoading,
    Entity\Validation
{
    use Document\MetaImplementation,
        Entity\LazyLoading\Implementation;
    
    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->cast();
    }
}
