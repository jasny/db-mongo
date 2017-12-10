<?php

namespace Jasny\DB\Mongo\Document;

use Jasny\DB\Mongo\TestDocumentMeta,
    Jasny\DB\Mongo\TestDocumentMetaEmpty,
    Jasny\DB\Mongo\TestHelper,
    Jasny\DB\Mongo\TypeCast;

/**
 * @covers Jasny\DB\Mongo\Document\MetaImplementation
 */
class MetaImplementationTest extends TestHelper
{
    /**
     * Provide data for testing 'getDBName' method
     *
     * @return array
     */
    public function getDBNameProvider()
    {
        return [
            [TestDocumentMeta::class, 'test_db'],
            [TestDocumentMetaEmpty::class, 'default']
        ];
    }

    /**
     * Test 'getDBName' method
     *
     * @dataProvider getDBNameProvider
     * @param string $class
     * @param string $expected
     */
    public function testGetDBName($class, $expected)
    {
        $result = $this->callProtectedMethod(new $class(), 'getDBName', []);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test 'getCollectionName' method
     */
    public function testGetCollectionName()
    {
        $document = new TestDocumentMeta();

        $result = $this->callProtectedMethod($document, 'getCollectionName', []);
        $this->assertEquals('other_test_collection', $result);

        TestDocumentMeta::$collection = null;
        $result = $this->callProtectedMethod($document, 'getCollectionName', []);
        $this->assertEquals('test_collection', $result);

        $document = new TestDocumentMetaEmpty();

        $result = $this->callProtectedMethod($document, 'getCollectionName', []);
        $this->assertEquals('test_document_meta_empties', $result);
    }

    /**
     * Test 'castForDB' method
     */
    public function testCastForDB()
    {
        $document = new TestDocumentMeta();

        $data = [
            'foo' => 'test',
            'bar' => '123',
            'zoo' => 'alice'
        ];

        $expected = [
            'bar' => 123,
            'zoo' => 'alice'
        ];

        $result = $this->callProtectedMethod($document, 'castForDB', [$data]);

        $this->assertSame($expected, $result);
    }

    /**
     * Test 'getIdProperty' method
     */
    public function testGetIdProperty()
    {
        $result = TestDocumentMeta::getIdProperty();
        $this->assertEquals('idField', $result);

        $result = TestDocumentMetaEmpty::getIdProperty();
        $this->assertEquals('id', $result);
    }

    /**
     * Test 'getFieldMap' method
     */
    public function testGetFieldMap()
    {
        $document = new TestDocumentMeta();

        $expected = [
            '_id' => 'idField',
            'bar_in_db' => 'bar'
        ];

        $result = $this->callProtectedMethod($document, 'getFieldMap', []);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test 'typeCast' method
     */
    public function testTypeCast()
    {
        $document = new TestDocumentMeta();

        $result = $this->callProtectedMethod($document, 'typeCast', ['foo']);

        $this->assertInstanceOf(TypeCast::class, $result);
        $this->assertEquals('foo', $result->getValue());
    }

    /**
     * Test 'setValues' method
     */
    public function testSetValues()
    {
        $document = $this->createPartialMock(TestDocumentMeta::class, ['cast']);
        $document->expects($this->once())->method('cast');

        $result = $document->setValues(['foo' => 'bar']);

        $this->assertSame($document, $result);
        $this->assertEquals('bar', $result->foo);
    }
}
