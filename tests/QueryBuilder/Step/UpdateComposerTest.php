<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\QueryBuilder\Step;

use Improved as i;
use Improved\Iterator\CombineIterator;
use Jasny\DB\Exception\InvalidUpdateOperationException;
use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\Mongo\QueryBuilder\Step\UpdateComposer;
use OverflowException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\QueryBuilder\Step\AbstractComposer
 * @covers \Jasny\DB\Mongo\QueryBuilder\Step\UpdateComposer
 */
class UpdateComposerTest extends TestCase
{
    public function provider()
    {
        return [
            ['set', '$set', 10],
            ['inc', '$inc', 10],
            ['dec', '$inc', -10],
            ['mul', '$mul', 10],
            ['div', '$mul', 0.1],
            ['push', '$push', 10],
            ['pull', '$pullAll', 10]
        ];
    }

    /**
     * @dataProvider provider
     */
    public function test(string $operator, $expectedOp, $expectedValue)
    {
        $input = new CombineIterator([['field' => 'foo', 'operator' => $operator]], [10]);

        $compose = new UpdateComposer();

        $iterator = $compose($input);
        $this->assertInstanceOf(\Iterator::class, $iterator);

        ['keys' => $info, 'values' => $callbacks] = i\iterable_separate($iterator);

        $this->assertCount(1, $info);
        $this->assertEquals(['field' => 'foo', 'operator' => $operator, 'value' => 10], $info[0]);

        $this->assertCount(1, $callbacks);
        $this->assertInstanceOf(\Closure::class, $callbacks[0]);

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('add')->with([$expectedOp => ['foo' => $expectedValue]]);

        ($callbacks[0])($query, 'foo', $operator, 10);
    }

    public function testPatch()
    {
        $value = ['one' => 'hi', 'two' => (object)['a' => 'AAA', 'b' => 'BBB']];
        $input = new CombineIterator([['field' => 'foo', 'operator' => 'patch']], [$value]);

        $compose = new UpdateComposer();

        $iterator = $compose($input);
        $this->assertInstanceOf(\Iterator::class, $iterator);

        ['keys' => $info, 'values' => $callbacks] = i\iterable_separate($iterator);

        $this->assertCount(1, $info);
        $this->assertEquals(['field' => 'foo', 'operator' => 'patch', 'value' => $value], $info[0]);

        $this->assertCount(1, $callbacks);
        $this->assertInstanceOf(\Closure::class, $callbacks[0]);

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('add')
            ->with(['$set' => ['foo.one' => 'hi', 'foo.two.a' => 'AAA', 'foo.two.b' => 'BBB']]);

        ($callbacks[0])($query, 'foo', 'patch', $value);
    }

    public function testIterate()
    {
        $info =[
            ['field' => 'foo', 'operator' => 'set'],
            ['field' => 'bar', 'operator' => 'inc'],
            ['field' => 'color', 'operator' => 'set']
        ];

        $values = [42, 99, 'blue'];

        $input = new CombineIterator($info, $values);

        $compose = new UpdateComposer();

        $iterator = $compose($input);
        $this->assertInstanceOf(\Iterator::class, $iterator);

        ['keys' => $info, 'values' => $callbacks] = i\iterable_separate($iterator);

        $this->assertCount(3, $info);
        $this->assertCount(3, $callbacks);
    }

    public function invalidInfoProvider()
    {
        return [
            ['$min', 'set', 42, "Invalid field '\$min (set)': Starting with '$' isn't allowed"],
            ['foo.$min', 'set', 42, "Invalid field 'foo.\$min (set)': Starting with '$' isn't allowed"],
            ['foo', 'dance', 42, "Invalid field 'foo (dance)': Unknown operator 'dance'"],
            ['foo', 'set', ['$min' => 10], "Invalid filter value for 'foo (set)': "
                . "Illegal array key '\$min', starting with '$' isn't allowed"],
            ['foo', 'set', [(object)['bar' => 22], (object)['$max' => 10]], "Invalid filter value for 'foo (set)': "
                . "Illegal object property '\$max', starting with '$' isn't allowed"]
        ];
    }

    /**
     * @dataProvider invalidInfoProvider
     */
    public function testInvalidFieldName(string $field, string $operator, $value, string $exceptionMsg)
    {
        $this->expectException(InvalidUpdateOperationException::class);
        $this->expectExceptionMessage($exceptionMsg);

        $input = new CombineIterator([['field' => $field, 'operator' => $operator]], [$value]);
        $compose = new UpdateComposer();

        $iterator = $compose($input);

        ['values' => $callbacks] = i\iterable_separate($iterator);

        $query = $this->createMock(Query::class);
        ($callbacks[0])($query, $field, $operator, $value);
    }

    public function testRecursionCircularReference()
    {
        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage("Unable to apply 'foo (set)'; possible circular reference");

        $objectA = new \stdClass();
        $objectB = new \stdClass();

        $objectA->b = $objectB;
        $objectB->a = $objectA;

        $input = new CombineIterator([['field' => 'foo', 'operator' => 'set']], [$objectA]);

        $compose = new UpdateComposer();
        $iterator = $compose($input);

        ['values' => $callbacks] = i\iterable_separate($iterator);

        $query = $this->createMock(Query::class);
        ($callbacks[0])($query, 'foo', 'set', $objectA);
    }
}
