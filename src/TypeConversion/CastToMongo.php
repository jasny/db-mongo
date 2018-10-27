<?php declare(strict_types=1);

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
    protected $conversions = [];


    public function __construct()
    {
        $toBsonDateTime = \Closure::fromCallable([$this, 'toBsonDateTime'])->bindTo(null);
        $this->conversions[\DateTimeInterface::class] = $toBsonDateTime;
    }

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
        $callback = (function($object) use ($convert) {
            $values = isset($convert) ? $convert($object) : object_get_properties($object, true);
            expect_type($values, ['array'], \UnexpectedValueException::class);

            return ['__pclass' => get_class($object)] + $values;
        })->bindTo(null);

        return $this->withConversion($class, $callback);
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
        $clone->conversions[$type] = $convert;

        return $clone;
    }

    /**
     * Convert non scalar value to mongodb type.
     *
     * @param iterable|\stdClass $item
     * @param int                $depth  Recursion depth
     * @return iterable|\stdClass
     */
    protected function applyRecursive($item, int $depth = 0)
    {
        if ($depth >= 32) {
            throw new \OverflowException("Unable to convert MongoDB type to value; possible circular reference");
        }

        $iterable = $item instanceof \stdClass ? (array)$item : $item;

        $generator = i\iterable_map($iterable, function($value) use ($depth) {
            return $this->convertValue($value, $depth + 1); // recursion
        });

        if ($item instanceof \Traversable) {
            return $generator;
        }

        $array = i\iterable_to_array($generator, true);

        return is_object($item) ? (object)$array : $array;
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
        if (is_scalar($value) || $value === null || $value instanceof BSON\Type) {
            return $value; // Quick return
        }

        if (is_iterable($value) || $value instanceof \stdClass) {
            return $this->applyRecursive($value, $depth);
        }

        if (is_object($value)) {
            $converted = $this->convertObject($value);
            return $this->convertValue($converted, $depth + 1); // recursion
        }

        if (is_resource($value)) {
            $converted = $this->convertResource($value);
            return $this->convertValue($converted, $depth + 1); // recursion
        }

        $type = get_type_description($value);
        throw new \UnexpectedValueException("Unable to cast $type to MongoDB type");
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

        if (!isset($this->conversions[$type])) {
            throw new \UnexpectedValueException("Unable to cast $type to MongoDB type");
        }

        return i\function_call($this->conversions[$type], $value);
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

        $convert = i\iterable_find($this->conversions, function($callable, string $class) use ($value) {
            return is_a($value, $class);
        });

        if (!isset($convert)) {
            $type = get_type_description($value);
            throw new \UnexpectedValueException("Unable to cast $type to MongoDB type");
        }

        return i\function_call($convert, $value);
    }


    /**
     * Convert DateTime object to BSON UTCDateTime.
     *
     * @param \DateTimeImmutable $date
     * @return BSON\UTCDateTime
     */
    protected function toBsonDateTime(\DateTimeInterface $date): BSON\UTCDateTime
    {
        return new BSON\UTCDateTime($date->getTimestamp() * 1000);
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
