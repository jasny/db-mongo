<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Write;

use Jasny\DB\Mongo\Common\ReadWriteTrait;
use Jasny\DB\Write\WriteInterface;

/**
 * Fetch data from a MongoDB collection
 */
class Writer implements WriteInterface
{
    use ReadWriteTrait;
    use Traits\SaveTrait;
    use Traits\UpdateTrait;
    use Traits\DeleteTrait;
}
