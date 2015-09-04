<?php

namespace Jasny\DB\Mongo\Document;

use Jasny\DB\Entity,
    Jasny\DB\Dataset;

/**
 * Mongo document as Active Record
 */
interface ActiveRecord extends
    Entity,
    Entity\ActiveRecord,
    Entity\Identifiable,
    Entity\SelfAware,
    Dataset
{ }
