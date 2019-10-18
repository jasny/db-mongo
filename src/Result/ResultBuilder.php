<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Result;

use Jasny\DB\FieldMap\ConfiguredFieldMap;
use Jasny\DB\FieldMap\FieldMapInterface;
use Jasny\DB\Mongo\TypeConversion\CastToPHP;
use Jasny\DB\Result\ResultBuilder as Base;

/**
 * Default result builder for MongoDB results.
 */
class ResultBuilder extends Base
{
    /**
     * ResultBuilder constructor.
     */
    public function __construct(?FieldMapInterface $fieldMap = null)
    {
        parent::__construct($fieldMap ?? new ConfiguredFieldMap(['_id' => 'id']));
    }
}
