<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\DataMapper,
    Jasny\DB\Mongo\DataMapper\Implementation as DataMapperImplementation;

/**
 * Concrete implementation of Data mapper, that maps TestEntityData class
 */
class TestEntityDataMapper implements DataMapper
{
    use DataMapperImplementation;

    /**
     * Entity class
     * @var string
     **/
    public static $entityClass;
}
