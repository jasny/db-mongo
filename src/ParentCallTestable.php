<?php

namespace Jasny\DB\Mongo;

/**
 * Testable implementation of calling parent method
 */
trait ParentCallTestable
{
    /**
     * Call parent methdod
     * @param  string $method  Method name to call
     * @return mixed
     */
    public function parent($method, ...$args)
    {
        return parent::$method(...$args);
    }
}
