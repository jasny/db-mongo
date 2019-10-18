<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\QueryBuilder\Step;

use Improved as i;
use Improved\Iterator\CombineIterator;
use Jasny\DB\Exception\InvalidUpdateOperationException;
use Jasny\DB\Mongo\QueryBuilder\Compose\UpdateComposer;
use Jasny\DB\Mongo\QueryBuilder\FilterQuery;
use OverflowException;
use PHPUnit\Framework\TestCase;

/**
 * The purpose of the compose step is to create callbacks that apply logic to the query object. To test if the callback
 *   works as expected, they're invoked as they would the during build step.
 *
 * @covers \Jasny\DB\Mongo\QueryBuilder\Compose\AbstractComposer
 * @covers \Jasny\DB\Mongo\QueryBuilder\Compose\UpdateComposer
 */
class UpdateComposerTest extends TestCase
{
    public function testSet()
    {
        $value = ['one' => 'hi', 'two' => (object)['a' => 'AAA', 'b' => 'BBB']];

        // Emulate the result of the update parser.
        $input = new CombineIterator([['field' => 'foo', 'operator' => 'set']], [$value]);

        $compose = new UpdateComposer();

        $iterator = $compose($input);
        $this->assertInstanceOf(\Iterator::class, $iterator);

        ['keys' => $info, 'values' => $callbacks] = i\iterable_separate($iterator);

        $this->assertCount(1, $info);
        $this->assertEquals(['field' => 'foo', 'operator' => 'set', 'value' => $value], $info[0]);

        $this->assertCount(1, $callbacks);
        $this->assertInstanceOf(\Closure::class, $callbacks[0]);

        $query = $this->createMock(FilterQuery::class);
        $query->expects($this->once())->method('add')->with(['$set' => ['foo' => $value]]);

        // Emulate the build step
        ($callbacks[0])($query, 'foo', 'set', $value);
    }

    public function testPatch()
    {
        $value = ['one' => 'hi', 'two' => (object)['a' => 'AAA', 'b' => 'BBB']];

        // Emulate the result of the update parser.
        $input = new CombineIterator([['field' => 'foo', 'operator' => 'patch']], [$value]);

        $compose = new UpdateComposer();

        $iterator = $compose($input);
        $this->assertInstanceOf(\Iterator::class, $iterator);

        ['keys' => $info, 'values' => $callbacks] = i\iterable_separate($iterator);

        $this->assertCount(1, $info);
        $this->assertEquals(['field' => 'foo', 'operator' => 'patch', 'value' => $value], $info[0]);

        $this->assertCount(1, $callbacks);
        $this->assertInstanceOf(\Closure::class, $callbacks[0]);

        $query = $this->createMock(FilterQuery::class);
        $query->expects($this->once())->method('add')
            ->with(['$set' => ['foo.one' => 'hi', 'foo.two.a' => 'AAA', 'foo.two.b' => 'BBB']]);

        // Emulate the build step
        ($callbacks[0])($query, 'foo', 'patch', $value);
    }

    public function arithmeticProvider()
    {
        return [
            'inc' => ['inc', '$inc', 10],
            'mul' => ['mul', '$mul', 10],
            'div' => ['div', '$mul', 0.1],
        ];
    }

    /**
     * @dataProvider arithmeticProvider
     */
    public function testArithmeticOperators(string $operator, $expectedOp, $expectedValue)
    {
        // Emulate the result of the update parser.
        $input = new CombineIterator([['field' => 'foo', 'operator' => $operator]], [10]);

        $compose = new UpdateComposer();

        $iterator = $compose($input);
        $this->assertInstanceOf(\Iterator::class, $iterator);

        ['keys' => $info, 'values' => $callbacks] = i\iterable_separate($iterator);

        $this->assertCount(1, $info);
        $this->assertEquals(['field' => 'foo', 'operator' => $operator, 'value' => 10], $info[0]);

        $this->assertCount(1, $callbacks);
        $this->assertInstanceOf(\Closure::class, $callbacks[0]);

        $query = $this->createMock(FilterQuery::class);
        $query->expects($this->once())->method('add')->with([$expectedOp => ['foo' => $expectedValue]]);

        // Emulate the build step
        ($callbacks[0])($query, 'foo', $operator, 10);
    }

    public function arrayProvider()
    {
        return [
            'push' => ['push', '$push', ['$each' => ['foo', 'bar']]],
            'set' => ['pull', '$pullAll', ['foo', 'bar']],
        ];
    }

    /**
     * @dataProvider arrayProvider
     */
    public function testArrayOperators(string $operator, $expectedOp, $expectedValue)
    {
        $input = new CombineIterator([['field' => 'foo', 'operator' => $operator]], [['foo', 'bar']]);

        $compose = new UpdateComposer();

        $iterator = $compose($input);
        $this->assertInstanceOf(\Iterator::class, $iterator);

        ['keys' => $info, 'values' => $callbacks] = i\iterable_separate($iterator);

        $this->assertCount(1, $info);
        $this->assertEquals(['field' => 'foo', 'operator' => $operator, 'value' => ['foo', 'bar']], $info[0]);

        $this->assertCount(1, $callbacks);
        $this->assertInstanceOf(\Closure::class, $callbacks[0]);

        $query = $this->createMock(FilterQuery::class);
        $query->expects($this->once())->method('add')->with([$expectedOp => ['foo' => $expectedValue]]);

        // Emulate the build step
        ($callbacks[0])($query, 'foo', $operator, ['foo', 'bar']);
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
            '$min'        => ['$min', 'set', 42, "Invalid field '\$min (set)': Starting with '$' isn't allowed"],
            'foo.$min'    => [
                'foo.$min',
                'set',
                42,
                "Invalid field 'foo.\$min (set)': Starting with '$' isn't allowed"
            ],
            'foo (dance)' => ['foo', 'dance', 42, "Invalid field 'foo (dance)': Unknown operator 'dance'"],
            '$min key'    => [
                'foo',
                'set',
                ['$min' => 10],
                "Invalid filter value for 'foo (set)': Illegal array key '\$min', starting with '$' isn't allowed"
            ],
            '$max subkey' => [
                'foo',
                'set',
                [(object)['bar' => 22], (object)['$max' => 10]],
                "Invalid filter value for 'foo (set)': Illegal object property '\$max', starting with '$' isn't allowed"
            ],
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

        $query = $this->createMock(FilterQuery::class);
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

        $query = $this->createMock(FilterQuery::class);
        ($callbacks[0])($query, 'foo', 'set', $objectA);
    }
}
