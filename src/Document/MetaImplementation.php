<?php

namespace Jasny\DB\Mongo\Document;

use Jasny\DB\Mongo;
use Jasny\DB\Entity;
use Doctrine\Common\Inflector\Inflector;

/**
 * Document implementation with Introspection and TypeCasting implementation
 */
trait MetaImplementation
{
    use BasicImplementation,
        Entity\Meta\Implementation,
        Entity\Validation\MetaImplementation
    {
        BasicImplementation::setValues as private _basic_setValues;
        BasicImplementation::jsonSerializeFilter insteadof Entity\Meta\Implementation;
    }

    /**
     * Get the database connection
     *
     * @codeCoverageIgnore
     * @return \Jasny\DB
     */
    protected static function getDB()
    {
        $name = static::getDBName();

        return \Jasny\DB::conn($name);
    }

    /**
     * Get name of database
     *
     * @return string
     */
    protected static function getDBName()
    {
        return static::meta()['db'] ?: 'default';
    }

    /**
     * Get the Mongo collection name.
     *
     * @return string
     */
    protected static function getCollectionName()
    {
        if (isset(static::$collection)) {
            $name = static::$collection;
        } elseif (isset(self::meta()['dbCollection'])) {
            $name = self::meta()['dbCollection'];
        } else {
            $class = preg_replace('/^.+\\\\/', '', static::getDocumentClass());
            $plural = Inflector::pluralize($class);
            $name = Inflector::tableize($plural);
        }

        return $name;
    }

    /**
     * Cast data to use in DB
     *
     * @param array $data
     * @return array
     */
    protected static function castForDB($data)
    {
        foreach ($data as $key => &$value) {
            $prop = trim(strstr($key, '(', true)) ?: $key; // Remove filter directives
            $meta = static::meta()->ofProperty($prop);

            if (isset($meta['dbSkip'])) {
                unset($data[$key]);
            } elseif (isset($meta['dbFieldType'])) {
                $value = Mongo\TypeCast::value($value)->to($meta['dbFieldType']);
            }
        }

        return $data;
    }

    /**
     * Get identifier property
     *
     * @return string
     */
    public static function getIdProperty()
    {
        foreach (static::meta()->ofProperties() as $prop => $meta) {
            if (isset($meta['id'])) {
                return $prop;
            }
        }

        return 'id';
    }

    /**
     * Get the field map.
     *
     * @return array
     */
    protected static function getFieldMap()
    {
        $fieldMap = ['_id' => static::getIdProperty()];

        foreach (static::meta()->ofProperties() as $prop => $meta) {
            if (isset($meta['dbFieldName']) && $meta['dbFieldName'] !== $prop) {
                $fieldMap[$meta['dbFieldName']] = $prop;
            }
        }

        return $fieldMap;
    }

    /**
     * Get type cast object
     *
     * @return Mongo\TypeCast
     */
    protected function typeCast($value)
    {
        $typecast = Mongo\TypeCast::value($value);

        $typecast->alias('self', get_class($this));
        $typecast->alias('static', get_class($this));

        return $typecast;
    }

    /**
     * Set the values.
     *
     * @param array|object $values
     * @return $this
     */
    public function setValues($values)
    {
        $this->_basic_setValues($values);
        $this->cast();

        return $this;
    }

}
