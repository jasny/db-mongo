<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\TypeConversion;

use Improved as i;
use MongoDB\BSON;

/**
 * Convert MongoDB type to PHP type.
 */
class CastToPHP extends AbstractTypeConversion
{
    /**
     * @var array<string, callable>
     */
    protected array $persistable = [];

    /**
     * @var array<callable>
     */
    protected array $bsonConversions = [];


    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->bsonConversions[BSON\UTCDateTime::class] = \Closure::fromCallable([$this, 'toDateTime']);

        $this->bsonConversions[BSON\ObjectId::class] = fn($bson) => (string)$bson;
        $this->bsonConversions[BSON\Binary::class] = fn($bson) => (string)$bson;
    }


    /**
     * Create service which persist objects of a class as if it implemented the BSON\Persistable interface.
     * If no $convert callback if specified, the `__set_state` method of the class is used.
     *
     * @param string        $class
     * @param callable|null $convert  Callback to convert associative array to object.
     * @return static
     */
    public function withPersistable(string $class, ?callable $convert = null)
    {
        $convert ??= \Closure::fromCallable([$this, 'classSetState']);

        $clone = clone $this;
        $clone->persistable[$class] = $convert;

        return $clone;
    }

    /**
     * Create service which can convert a BSON types to a PHP type.
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
     * Create service which can convert a BSON binary to a PHP type.
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

        try {
            if ($value instanceof \stdClass && isset($value->__pclass)) {
                $value = $this->convertPersistable($value);
            }

            if ($value instanceof BSON\Type) {
                $value = $this->convertBSON($value);
            }

            if (is_iterable($value) || $value instanceof \stdClass) {
                $value = $this->applyRecursive($value, $depth);
            }
        } catch (\Exception $exception) {
            // Soft fail when value can't be converted.
            trigger_error($exception->getMessage(), E_USER_WARNING);
        }

        return $value;
    }


    /**
     * Cast persistable object from MongoDB back to original class.
     *
     * @param \stdClass $value
     * @return object
     */
    protected function convertPersistable(\stdClass $value)
    {
        $pclass = $value->__pclass;

        $convert = i\iterable_find($this->persistable, function ($callback, $persistable) use ($pclass) {
            return is_a($pclass, $persistable, true);
        });

        if ($convert === null) {
            throw new \UnexpectedValueException(
                "Won't cast object to '{$value->__pclass}': class not marked as persistable"
            );
        }

        $data = (array)$value;
        unset($data['__pclass']);
        $class = $value->__pclass;

        $object = ($convert)($class, $data);

        return i\type_check($object, $class, new \UnexpectedValueException());
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
            throw new \UnexpectedValueException("Unable to convert $type object to PHP type");
        }

        return ($this->bsonConversions[$type])($value);
    }


    /**
     * Convert BSON UTCDateTime to DateTime object.
     */
    protected function toDateTime(BSON\UTCDateTime $bsonDate): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->setTimestamp($bsonDate->toDateTime()->getTimestamp());
    }

    /**
     * Convert persisted object back to original class using __set_state().
     *
     * @param string $class
     * @param array  $data
     * @return object|array
     */
    protected function classSetState(string $class, array $data)
    {
        if (!method_exists($class, '__set_state')) {
            throw new \LogicException("Won't cast object to '{$class}': class doesn't have a __set_state() method");
        }

        return ([$class, '__set_state'])($data);
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
