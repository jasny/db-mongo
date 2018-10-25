<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\TypeConversion;

use Improved as i;
use MongoDB\BSON;
use function Jasny\get_type_description;
use function Jasny\expect_type;
use function Jasny\object_get_properties;

/**
 * Cast PHP type to MongoDB type.
 */
class CastToMongo
{
    /**
     * @var array<string, callable>
     */
    protected $custom = [];


    /**
     * Create service which persist objects of a class as if it implemented the BSON\Persistable interface.
     * Classes that are Traversable or extend stdClass, can't implement custom logic.
     *
     * @param string   $class
     * @param callable $convert  Callback to convert object to associative array
     * @return static
     */
    public function withPersistable(string $class, ?callable $convert = null)
    {
        $callback = function($object) use ($convert) {
            $values = isset($convert) ? $convert($object) : object_get_properties($object, true);
            expect_type($values, ['array'], \UnexpectedValueException::class);

            return ['__pclass' => get_class($object)] + $values;
        };

        return $this->withConversion($class, $callback->bindTo(null));
    }

    /**
     * Create service which can convert specified class or resource.
     *
     * @param string   $type         Class name or resource type (eg "stream resource")
     * @param callable $convert
     * @return static
     */
    public function withConversion(string $type, callable $convert)
    {
        $clone = clone $this;
        $clone->custom[$type] = $convert;

        return $clone;
    }

    /**
     * Convert non scalar value to mongodb type.
     *
     * @param object|iterable $value
     * @param int             $depth  Recursion depth
     * @return \Generator
     */
    protected function convertStructured($value, $depth): \Generator
    {
        foreach ($value as $key => $item) {
            yield $key => $this->convertValue($item, $depth + 1); // Recursion
        }
    }

    /**
     * Convert value to MongoDB type, with recursion protection.
     *
     * @param mixed $value
     * @param int   $depth
     * @return mixed
     */
    protected function convertValue($value, $depth = 0)
    {
        if ($depth >= 100) {
            throw new \OverflowException("Unable to convert value to MongoDB type; possible circular reference");
        }

        switch (true) {
            case $value === null:
            case is_scalar($value):
            case $value instanceof BSON\Type:
                return $value;

            case is_array($value):
                $iterator = $this->convertStructured($value, $depth);
                return i\iterable_to_array($iterator, true);
            case $value instanceof \Traversable:
                return $this->convertStructured($value, $depth);
            case $value instanceof \stdClass:
                $iterator = $this->convertStructured($value, $depth);
                return (object)i\iterable_to_array($iterator, true);

            case $value instanceof \DateTimeInterface:
                return new BSON\UTCDateTime($value->getTimestamp() * 1000);

            case is_object($value):
                $converted = $this->convertObject($value);
                return $this->convertValue($converted, $depth + 1); // recursion
            case is_resource($value):
                $converted = $this->convertResource($value);
                return $this->convertValue($converted, $depth + 1); // recursion

            default:
                $type = get_type_description($value);
                throw new \UnexpectedValueException("Unable to cast $type to MongoDB type");
        }
    }

    /**
     * Convert resource to MongoDB type.
     *
     * @param resource $value
     * @return mixed
     */
    protected function convertResource($value)
    {
        expect_type($value, 'resource');

        $type = get_resource_type($value) . ' resource';

        if (!isset($this->custom[$type])) {
            throw new \UnexpectedValueException("Unable to cast $type to MongoDB type");
        }

        return i\function_call($this->custom[$type], $value);
    }

    /**
     * Convert object to MongoDB type.
     *
     * @param object $value
     * @return mixed
     */
    protected function convertObject($value)
    {
        expect_type($value, 'object');

        $convert = i\iterable_find($this->custom, function($callable, string $class) use ($value) {
            return is_a($value, $class);
        });

        if (!isset($convert)) {
            $type = get_type_description($value);
            throw new \UnexpectedValueException("Unable to cast $type to MongoDB type");
        }

        return i\function_call($convert, $value);
    }


    /**
     * Invoke conversion
     *
     * @param mixed $value
     * @return mixed
     */
    final public function __invoke($value)
    {
        return $this->convertValue($value);
    }
}
