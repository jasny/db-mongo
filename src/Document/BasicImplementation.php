<?php

namespace Jasny\DB\Mongo\Document;

use Jasny\Meta;
use Jasny\DB\Data;
use Jasny\DB\Entity;
use Jasny\DB\Entity\LazyLoading;
use Jasny\DB\EntitySet;
use Jasny\DB\FieldMapping;
use Jasny\DB\Mongo\Dataset;
use Jasny\DB\Dataset\Sorted;
use Jasny\DB\Entity\ChangeAware;

/**
 * Static methods to interact with a collection (as document)
 *
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait BasicImplementation
{
    use Entity\Implementation,
        FieldMapping\Implementation,
        Dataset\Implementation
    {
        Entity\Implementation::fromData as private _entity_fromData;
    }


    /**
     * Get the field map.
     *
     * @return array
     */
    protected static function getFieldMap()
    {
        return ['_id' => static::getIdProperty()];
    }

    /**
     * Get the property used to identify the document
     *
     * @return string
     */
    public static function getIdProperty()
    {
        return 'id';
    }

    /**
     * Get document id.
     *
     * @return string
     */
    public function getId()
    {
        $prop = static::getIdProperty();
        return $this->$prop;
    }

    /**
     * Get the data that needs to be saved in the DB
     *
     * @return array
     */
    public function toData()
    {
        $values = call_user_func('get_object_vars', $this); // `call_user_func` is used to only get public properties

        foreach ($values as &$item) {
            if ($item instanceof Entity\Identifiable) {
                $item = $this->childEntityToId($item);
            } elseif ($item instanceof EntitySet && is_a($item->getEntityClass(), Entity\Identifiable::class, true)) {
                $ids = [];
                foreach ($item as $child) {
                    $ids[] = $this->childEntityToId($child);
                }
                $item = $ids;
            } elseif ($item instanceof Data) {
                $item = $item->toData();
            }
        }

        if ($this instanceof Sorted && method_exists($this, 'prepareDataForSort')) {
            $values += $this->prepareDataForSort();
        }

        $casted = static::castForDB($values);
        $data = static::mapToFields($casted);

        if (array_key_exists('_id', $data) && is_null($data['_id'])) {
            unset($data['_id']);
        }

        return $data;
    }

    /**
     * Convert child to an id
     *
     * @param Entity $item
     * @return \MongoId|mixed
     */
    protected function childEntityToId(Entity $item)
    {
        if (
            $item instanceof Meta\Introspection &&
            is_scalar($item::getIdProperty()) &&
            $item::meta()->ofProperty($item::getIdProperty())['dbFieldType'] === '\MongoId'
        ) {
            $id = new \MongoId($item->getId());
        } else {
            $id = $item->getId();
        }

        return $id;
    }

    /**
     * Save the document
     *
     * @param array $opts
     * @return $this
     */
    public function save(array $opts = [])
    {
        if ($this instanceof Entity\LazyLoading && $this->isGhost()) {
            $msg = "This " . get_called_class() . " entity isn't fully loaded. First expand, than edit, than save.";
            throw new \Exception("Unable to save: $msg");
        }

        $data = $this->toData();

        if ($this instanceof ChangeAware && $this->isNew()) {
            static::getCollection()->insert($data);
        } else {
            static::getCollection()->save($data);
        }

        $idProp = static::getIdProperty();
        $this->$idProp = $data['_id'];
        $this->cast();

        return $this;
    }

    /**
     * Delete the document
     *
     * @codeCoverageIgnore
     * @param array $opts
     * @return $this
     */
    public function delete(array $opts = [])
    {
        $properties = [static::getIdProperty() => $this->getId()];
        $filter = static::castForDB($properties);
        $query = static::mapToFields($filter);

        static::getCollection()->remove($query);
        return $this;
    }

    /**
     * Reload the entity from the DB
     *
     * @codeCoverageIgnore
     * @param array $opts
     * @return $this|false
     */
    public function reload(array $opts = [])
    {
        $entity = static::fetch($this, $opts);
        if (!$entity) {
            return false;
        }

        foreach ((array)$entity as $prop => $value) {
            // Ignore private and protected properties
            if ($prop[0] !== "\0") {
                $this->$prop = $value;
            }
        }

        return $this;
    }

    /**
     * Check no other document with the same value of the property exists
     *
     * @codeCoverageIgnore
     * @param string        $property
     * @param array|string  $group     List of properties that should match
     * @param array         $opts
     * @return boolean
     */
    public function hasUnique($property, $group = null, array $opts = [])
    {
        if (!isset($this->$property)) {
            return true;
        }

        $filter = [static::getIdProperty() . '(not)' => $this->getId(), $property => $this->$property];
        foreach ((array)$group as $prop) {
            if (isset($this->$prop)) $filter[$prop] = $this->$prop;
        }

        return !static::exists($filter, $opts);
    }

    /**
     * Prepare result when casting object to JSON
     *
     * @return object
     */
    public function jsonSerialize()
    {
        if ($this instanceof LazyLoading) {
            $this->expand();
        }

        $values = $this->getValues();

        foreach ($values as &$value) {
            if ($value instanceof \DateTime) {
                $value = $value->format(\DateTime::ISO8601);
            } elseif ($value instanceof \MongoId) {
                $value = (string)$value;
            }
        }

        return $this->jsonSerializeFilter((object)$values);
    }

    /**
     * Convert loaded values to an entity
     *
     * @param array|object $values
     * @return static
     */
    public static function fromData($values)
    {
        if (is_object($values)) {
            $values = (array)$values;
        }

        $mapped = static::mapFromFields($values);
        return static::_entity_fromData($mapped);
    }
}
