<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\QueryBuilder;

use Improved as i;
use Jasny\DB\Mongo\QueryBuilder\SaveQueryBuildStep;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\QueryBuilder\SaveQueryBuildStep
 */
class SaveQueryBuildStepTest extends TestCase
{
    public function test()
    {
        $batches = [
            [
                ['_id' => 10, 'foo' => 42, 'color' => 'red'],
                ['_id' => 12, 'foo' => 99, 'color' => 'green'],
                ['_id' => null, 'foo' => 3, 'color' => 'blue'],
            ],
            [
                ['_id' => 17, 'color' => 'red'],
                ['_id' => 22, 'foo' => 77, 'color' => 'red'],
                ['_id' => 8, 'foo' => 8, 'color' => 'green'],
            ],
            [
                ['_id' => null, 'foo' => 4]
            ]
        ];

        $build = new SaveQueryBuildStep();

        $statements = $build($batches);

        $expected = [
            [
                ['replaceOne' => [['_id' => 10], ['foo' => 42, 'color' => 'red']]],
                ['replaceOne' => [['_id' => 12], ['foo' => 99, 'color' => 'green']]],
                ['insertOne' => ['foo' => 3, 'color' => 'blue']]
            ],
            [
                ['replaceOne' => [['_id' => 17], ['color' => 'red']]],
                ['replaceOne' => [['_id' => 22], ['foo' => 77, 'color' => 'red']]],
                ['replaceOne' => [['_id' => 8], ['foo' => 8, 'color' => 'green']]]
            ],
            [
                ['insertOne' => ['foo' => 4]]
            ]
        ];

        $this->assertInstanceOf(\Iterator::class, $statements);
        $this->assertEquals($expected, i\iterable_to_array($statements, false));
    }
}
