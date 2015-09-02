<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Entity\Meta,
    Jasny\DB\FieldMapping,
    Jasny\DB\Entity\LazyLoading;

/**
 * Base class for Mongo Documents
 */
abstract class Document implements
    Document\ActiveRecord,
    Meta,
    FieldMapping,
    LazyLoading
{
    use Document\MetaImplementation,
        LazyLoading\Implementation;
    
    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->cast();
    }
}
