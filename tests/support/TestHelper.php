<?php

namespace Jasny\DB\Mongo;

/**
 * Additional test methods
 */
abstract class TestHelper extends \PHPUnit_Framework_TestCase
{
    /**
     * Set a private or protected property of the given object
     *
     * @param object $object
     * @param string $property
     * @param mixed  $value
     */
    protected function setPrivateProperty($object, $property, $value)
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException("Expected an object, got a " . gettype($object));
        }

        $refl = new \ReflectionProperty($object, $property);
        $refl->setAccessible(true);
        $refl->setValue($object, $value);
    }

    /**
     * Get private or protected property value
     *
     * @param object $object
     * @param string $property
     * @return mixed
     */
    protected function getPrivateProperty($object, $property)
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException("Expected an object, got a " . gettype($object));
        }

        $refl = new \ReflectionObject($object);
        $property = $refl->getProperty($property);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * Call protected method on some object
     *
     * @param object $object
     * @param string $name   Method name
     * @param array $args
     * @return mixed         Result of method call
     */
    protected function callProtectedMethod($object, $name, $args)
    {
        $class = new \ReflectionClass($object);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}
