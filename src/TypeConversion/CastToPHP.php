<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\TypeConversion;

use Improved as i;
use MongoDB\BSON;
use function Jasny\array_without;
use function Jasny\expect_type;

/**
 * Convert MongoDB type to PHP type.
 */
class CastToPHP
{
    /**
     * @var array<string, callable>
     */
    protected $persistable = [];

    /**
     * @var array<callable>
     */
    protected $bsonConversions = [];


    /**
     * Class constructor
     */
    public function __construct()
    {
        $toDateTime = \Closure::fromCallable([$this, 'toDateTime'])->bindTo(null);
        $this->bsonConversions[BSON\UTCDateTime::class] = $toDateTime;

        $toString = \Closure::fromCallable([$this, 'toString'])->bindTo(null);
        $this->bsonConversions[BSON\ObjectId::class] = $toString;
        $this->bsonConversions[BSON\Binary::class] = $toString;
    }


    /**
     * Create service which persist objects of a class as if it implemented the BSON\Persistable interface.
     *
     * @param string   $class
     * @param callable $convert  Callback to convert associative array to object
     * @return static
     */
    public function withPersistable(string $class, ?callable $convert = null)
    {
        $convert = $convert ?? (function(string $class, array $data) {
            return method_exists($class,'__set_state')
                ? $class::__set_state($data)
                : new $class($data);
        })->bindTo(null);

        $clone = clone $this;
        $clone->persistable[$class] = $convert;

        return $clone;
    }

    /**
     * Create service which can convert a BSON types to a PHP type
     *
     * @param string   $class    Class name or resource type (eg "stream resource")
     * @param callable $convert  Callback to convert BSON type
     * @return static
     */
    public function withBSON(string $class, callable $convert)
    {
        if (!is_a($class, BSON\Type::class, true)) {
            throw new \InvalidArgumentException("Class '$class' doesn't implement MongoDB\BSON\Type");
        }

        $clone = clone $this;
        $clone->bsonConversions[$class] = $convert;

        return $clone;
    }

    /**
     * Create service which can convert a BSON binary to a PHP type
     *
     * @param int      $type     A MongoDB\BSON\Binary::TYPE_* constant or 128 to 256
     * @param callable $convert  Callback to convert BSON type
     * @return static
     */
    public function withBinary(int $type, callable $convert)
    {
        if ($type < 0 || $type > 256) {
            throw new \InvalidArgumentException("Type should be between 0 and 256");
        }

        $clone = clone $this;
        $clone->bsonConversions[$type] = $convert;

        return $clone;
    }


    /**
     * Recursively convert BSON to PHP
     *
     * @param iterable|\stdClass $item
     * @param int $depth
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

        return $item instanceof \stdClass ? (object)$array : $array;
    }

    /**
     * Convert a MongoDB type to normal PHP value.
     *
     * @param mixed $value
     * @param int   $depth
     * @return mixed
     */
    protected function convertValue($value, int $depth = 0)
    {
        if (is_scalar($value) || $value === null) {
            return $value; // Quick return
        }

        if ($value instanceof \stdClass && isset($value->__pclass)) {
            $value = $this->convertPersistable($value);
        }

        if ($value instanceof BSON\Type) {
            $value = $this->convertBSON($value);
        }

        if (is_iterable($value) || $value instanceof \stdClass) {
            $value = $this->applyRecursive($value, $depth);
        }

        return $value;
    }


    /**
     * Cast persistable stdClass object
     *
     * @param \stdClass $value
     * @return object
     */
    protected function convertPersistable(\stdClass $value)
    {
        $pclass = $value->__pclass;

        $convert = i\iterable_find($this->persistable, function($callback, $persistable) use ($pclass) {
            return is_a($pclass, $persistable, true);
        });

        if ($convert === null) {
            trigger_error("Won't cast object to '$value->__pclass': class not marked as persistable", E_USER_WARNING);
            return $value;
        }

        $data = array_without((array)$value, ['__pclass']);
        $class = $value->__pclass;

        $object = i\function_call($convert, $class, $data);
        expect_type($object, $class, \UnexpectedValueException::class);

        return $object;
    }

    /**
     * Convert a MongoDB BSON type to normal PHP value.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function convertBSON(BSON\Type $value)
    {
        $type = $value instanceof BSON\Binary && isset($this->bsonConversions[$value->getType()])
            ? $value->getType()
            : get_class($value);

        if (!isset($this->bsonConversions[$type])) {
            trigger_error("Unable to convert $type object to PHP type", E_USER_WARNING);
            return $value;
        }

        return i\function_call($this->bsonConversions[$type], $value);
    }


    /**
     * Convert BSON UTCDateTime to DateTime object.
     *
     * @param BSON\UTCDateTime $bson
     * @return \DateTimeImmutable
     */
    protected function toDateTime(BSON\UTCDateTime $bsonDate): \DateTimeImmutable
    {
        return (new \DateTimeImmutable)->setTimestamp($bsonDate->toDateTime()->getTimestamp());
    }

    /**
     * Convert BSON object to string
     *
     * @param BSON\Type $bson
     * @return string
     */
    protected function toString(BSON\Type $bson): string
    {
        return (string)$bson;
    }


    /**
     * Invoke conversion
     *
     * @param mixed $value
     * @return mixed
     */
    public function __invoke($value)
    {
        return $this->convertValue($value);
    }
}
