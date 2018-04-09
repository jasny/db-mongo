<?php

namespace Jasny\DB\Mongo;

/**
 * Classes for correct testing of `ParentCallTestable` trait
 */
class TestableBase {
    public function foo($a, $b)
    {
        return $a .= ' base ' . $b;
    }
}

class TestableMiddle extends TestableBase {
    use ParentCallTestable;

    public function foo($a, $b)
    {
        return $this->parent('foo', $a . ' middle', $b);
    }
}

class TestableChild extends TestableMiddle {

}
