<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Entity,
    Jasny\DB\Recordset;

/**
 * Interface for a Mongo document as Active Record
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
interface Document extends Entity, Entity\Identifiable, Entity\ActiveRecord, Recordset
{ }
