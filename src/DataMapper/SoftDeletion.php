<?php

namespace Jasny\DB\Mongo\Document;

use Jasny\DB\DataMapper, \Jasny\DB\Recordset;

/**
 * Interface for document that supports soft deletion.
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.com/db-mongo
 */
interface SoftDeletion extends DataMapper\SoftDeletion, Recordset\WithTrash
{}
