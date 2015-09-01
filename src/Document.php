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
    use Document\WithMeta,
        LazyLoading\Implementation;
}
