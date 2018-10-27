<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\QueryBuilder\Step;

use Improved\Iterator\CombineIterator;
use Jasny\DB\Mongo\QueryBuilder\Step\BuildStep;
use Jasny\DB\Mongo\QueryBuilder\OptionConverter;
use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\Option;
use Jasny\TestHelper;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\QueryBuilder\Step\BuildStep
 */
class BuildStepTest extends TestCase
{
    use TestHelper;

    public function test()
    {
        $option = $this->createMock(Option::class);
        /** @var OptionConverter|MockObject $optionConverter */
        $optionConverter = $this->createMock(OptionConverter::class);
        $optionConverter->expects($this->once())->method('convert')
            ->with([$option])->willReturn(['limit' => 10]);

        $callbacks = [];

        $callbacks[] = $this->createCallbackMock($this->once(), function(InvocationMocker $invoke) use ($option) {
            $invoke->with($this->isInstanceOf(Query::class), 'foo', '', 42, [$option]);
            $invoke->willReturnCallback(function(Query $query) {
                $query->add(['foo' => 'XLII']);
            });
        });

        $callbacks[] = $this->createCallbackMock($this->once(), function(InvocationMocker $invoke) use ($option) {
            $invoke->with($this->isInstanceOf(Query::class), 'color', 'not', 'blue', [$option]);
        $invoke->willReturnCallback(function(Query $query) {
                $query->add(['color' => ['$not' => 'blue']]);
            });
        });

        $info = [
            ['field' => 'foo', 'operator' => '', 'value' => 42],
            ['field' => 'color', 'operator' => 'not', 'value' => 'blue']
        ];

        $build = new BuildStep($optionConverter);
        $query = $build(new CombineIterator($info, $callbacks), [$option]);

        $this->assertInstanceOf(Query::class, $query);
        $this->assertEquals(['foo' => 'XLII', 'color' => ['$not' => 'blue']], $query->toArray());
    }
}
