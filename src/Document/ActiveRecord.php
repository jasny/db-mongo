<?php

namespace Jasny\DB\Mongo\Document;

use Jasny\DB\Entity,
    Jasny\DB\Recordset;

/**
 * Mongo document as Active Record
 */
interface ActiveRecord extends
    Entity,
    Entity\Identifiable,
    Entity\ActiveRecord,
    Entity\UniqueProperties,
    Recordset
{ }

