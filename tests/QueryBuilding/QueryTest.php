<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\QueryBuilding;

use Jasny\DB\Mongo\QueryBuilding\Query;
use PHPUnit\Framework\TestCase;

class QueryTest extends TestCase
{
    public function testAdd()
    {
        $query = new Query();
        $query->add(['foo' => 42, 'bar' => 99]);
        $query->add(['color' => 'blue']);

        $this->assertEquals(['foo' => 42, 'bar' => 99, 'color' => 'blue'], $query->toArray());
    }

    public function testAddOrStatement()
    {
        $query = new Query();
        $query->add(['$or' => ['foo' => 42, 'bar' => 99]]);
        $query->add(['color' => 'blue']);

        $expected = ['$and' => [
            ['$or' => ['foo' => 42, 'bar' => 99]],
            ['color' => 'blue']
        ]];
        $this->assertEquals($expected, $query->toArray());
    }

    public function testOptions()
    {
        $query = new Query(['limit' => 10]);
        $query->setOption('skip', 40);

        $this->assertEquals(['limit' => 10, 'skip' => 40], $query->getOptions());

        $query->setOption('limit', 20);

        $this->assertEquals(['limit' => 20, 'skip' => 40], $query->getOptions());
    }
}
