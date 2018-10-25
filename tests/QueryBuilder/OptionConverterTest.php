<?php

namespace Jasny\DB\Mongo\Tests\QueryBuilder;

use Jasny\DB\Option as opt;
use Jasny\DB\Option\QueryOptionInterface;
use Jasny\DB\Mongo\QueryBuilder\OptionConverter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\QueryBuilder\OptionConverter
 */
class OptionConverterTest extends TestCase
{
    public function testFields()
    {
        $option = opt\fields('foo', 'bar', 'qux');

        $converter = new OptionConverter();
        $result = $converter->convert([$option]);

        $this->assertEquals(['projection' => ['foo' => 1, 'bar' => 1, 'qux' => 1]], $result);
    }

    public function testOmit()
    {
        $option = opt\omit('foo', 'bar', 'qux');

        $converter = new OptionConverter();
        $result = $converter->convert([$option]);

        $this->assertEquals(['projection' => ['foo' => -1, 'bar' => -1, 'qux' => -1]], $result);
    }

    public function limitProvider()
    {
        return [
            [['limit' => 10], opt\limit(10)],
            [['limit' => 10, 'skip' => 40], opt\limit(10, 40)],
            [['limit' => 10, 'skip' => 40], opt\page(5, 10)]
        ];
    }

    /**
     * @dataProvider limitProvider
     */
    public function testLimit($expected, QueryOptionInterface $option)
    {
        $converter = new OptionConverter();
        $result = $converter->convert([$option]);

        $this->assertEquals($expected, $result);
    }

    public function testSort()
    {
        $option = opt\sort('foo', 'bar', '~qux');

        $converter = new OptionConverter();
        $result = $converter->convert([$option]);

        $this->assertEquals(['sort' => ['foo' => 1, 'bar' => 1, 'qux' => -1]], $result);
    }


    public function testMultipleOpts()
    {
        $opts = [
            opt\fields('foo', 'bar'),
            opt\fields('color'),
            opt\omit('bar', 'qux'),
            opt\limit(10),
            opt\page(3, 20)
        ];

        $converter = new OptionConverter();
        $result = $converter->convert($opts);

        $expected = [
            'projection' => ['foo' => 1, 'bar' => -1, 'color' => 1, 'qux' => -1],
            'limit' => 20,
            'skip' => 40
        ];

        $this->assertEquals($expected, $result);
    }

    public function testInvoke()
    {
        $option = opt\fields('foo', 'bar', 'qux');

        $convert = new OptionConverter();
        $result = $convert([$option]);

        $this->assertEquals(['projection' => ['foo' => 1, 'bar' => 1, 'qux' => 1]], $result);
    }


    /**
     * @expectedException \Jasny\DB\Exception\InvalidOptionException
     * @expectedExceptionMessage Unsupported query option class 'UnsupportedOption'
     */
    public function testUnsupportedOption()
    {
        $option = $this->getMockBuilder(QueryOptionInterface::class)
            ->setMockClassName('UnsupportedOption')
            ->disableOriginalConstructor()
            ->disableProxyingToOriginalMethods()
            ->getMock();

        $converter = new OptionConverter();
        $converter->convert([$option]);
    }

    /**
     * @expectedException \Jasny\DB\Exception\InvalidOptionException
     * @expectedExceptionMessage Unknown query option 'funky'
     */
    public function testUnkownFieldsOption()
    {
        $option = new opt\FieldsOption('funky', ['foo', 'bar']);

        $converter = new OptionConverter();
        $converter->convert([$option]);
    }
}
