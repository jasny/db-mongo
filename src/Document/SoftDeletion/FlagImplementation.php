<?php

namespace Jasny\DB\Mongo\Document\SoftDeletion;

use Jasny\DB\Mongo\DB;

/**
 * Implementation of soft deletion using a flag (for documents).
 *
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait FlagImplementation
{
    /**
     * Convert a Jasny DB styled filter to a MongoDB query.
     *
     * @param array $filter
     * @param array $opts
     * @return array
     */
    protected static function filterToQuery($filter, array $opts = [])
    {
        $filter = static::castForDB($filter);
        $filter = static::mapToFields($filter);

        $opts += ['deleted' => false]; // Defaults to no deleted

        if ($opts['deleted'] === 'only' || in_array('from-trash', $opts, true)) {
            $filter['_deleted'] = true;
        } elseif ($opts['deleted'] !== 'included' && !in_array('include-trash', $opts, true)) {
            $filter['_deleted'] = null;
        }

        return DB::filterToQuery($filter);
    }

    /**
     * Check if document is flagged as deleted
     *
     * @return boolean
     */
    public function isDeleted()
    {
        $filter = static::idToFilter($this);
        $query = static::filterToQuery($filter, ['from-trash']);

        if (empty($query['_id'])) {
            return false;
        }

        return static::getCollection()->count($query) > 0;
    }

    /**
     * Delete the document
     *
     * @param array $opts
     * @return $this
     */
    public function delete(array $opts = [])
    {
        $filter = static::idToFilter($this);
        $query = static::filterToQuery($filter);

        static::getCollection()->update($query, ['$set' => ['_deleted' => true]]);
        return $this;
    }

    /**
     * Undelete the document
     *
     * @param array $opts
     * @return $this
     */
    public function undelete(array $opts = [])
    {
        $filter = static::idToFilter($this);
        $query = static::filterToQuery($filter, array_merge($opts, ['from-trash']));

        static::getCollection()->update($query, ['$unset' => ['_deleted' => 1]]);
        return $this;
    }

    /**
     * Purge a deleted document
     *
     * @param array $opts
     * @return $this
     */
    public function purge(array $opts = [])
    {
        if (!$this->isDeleted()) {
            throw new \Exception("Won't purge: " . get_called_class() . " isn't deleted");
        }

        $filter = static::idToFilter($this);
        $query = static::filterToQuery($filter, array_merge($opts, ['from-trash']));

        static::getCollection()->remove($query);
        return $this;
    }

    /**
     * Purge all deleted documents
     *
     * @param array $opts
     */
    public static function purgeAll(array $opts = [])
    {
        static::getCollection()->remove(['_deleted' => true]);
    }
}
