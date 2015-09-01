<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Dataset,
    Jasny\DB\FieldMapping;

/**
 * Data Mapper for fetching and storing entities using Mongo.
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
class DataMapper implements Jasny\DB\DataMapper, Dataset, FieldMapping
{
    use DataMapper\Basics;    
}
