<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\BasicEntity;

/**
 * Concrete implementation of abstract class BasicEntity
 */
class TestEntity extends BasicEntity
{
    /**
     * @var DateTime
     **/
    public $date;

    /**
     * @var string
     **/
    public $zoo;
}
