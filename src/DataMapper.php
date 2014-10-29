<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Recordset;

/**
 * Data Mapper for fetching and storing entities using Mongo.
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
interface DataMapper extends Jasny\DB\DataMapper, Recordset
{
}
