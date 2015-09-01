<?php

namespace Jasny\DB\Mongo\Document;

use Jasny\DB\Entity,
    Jasny\DB\Dataset;

/**
 * Interface for document that supports soft deletion.
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.com/db-mongo
 */
interface SoftDeletion extends Entity\SoftDeletion, Dataset\WithTrash
{}
