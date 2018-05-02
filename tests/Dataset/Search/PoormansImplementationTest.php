<?php

namespace Jasny\DB\Mongo\Dataset\Search;

use Jasny\DB\Mongo\TestDocumentBasic,
    Jasny\DB\Mongo\TestDocumentMetaSearch,
    Jasny\DB\Mongo\TestDocumentBasicEmptyFilter,
    Jasny\DB\Mongo\TestHelper,
    Jasny\DB\Mongo\TypeCast,
    Jasny\DB\Mongo\Collection,
    Jasny\DB\Mongo\DB,
    Jasny\DB\Mongo\TestDocumentLazy,
    Jasny\DB\Mongo\Cursor;

/**
 * @covers Jasny\DB\Mongo\Dataset\Search\PoormansImplementation
 */
class PoormansImplementationTest extends TestHelper
{
    /**
     * Test 'getSearchFields' method, is search fields are explicitly defined
     */
    public function testGetSearchFieldsDefined()
    {
        TestDocumentBasic::$searchFields = ['foo', 'bar'];

        $document = $this->createPartialMock(TestDocumentBasic::class, []);
        $result = $this->callProtectedMethod($document, 'getSearchFields', []);

        $this->assertSame(['foo', 'bar'], $result);
    }

    /**
     * Test 'getSearchFields' method, if search fields are not defined, and class does not implement TypeCasting
     */
    public function testGetSearchFieldsNoTypeCasting()
    {
        TestDocumentBasic::$searchFields = null;

        $document = $this->createPartialMock(TestDocumentBasic::class, []);
        $result = $this->callProtectedMethod($document, 'getSearchFields', []);

        $this->assertSame([], $result);
    }

    /**
     * Test 'getSearchFields' method, if fields are not defined explicitly
     */
    public function testGetSearchFields()
    {
        TestDocumentMetaSearch::$searchFields = null;

        $document = $this->createPartialMock(TestDocumentMetaSearch::class, []);
        $result = $this->callProtectedMethod($document, 'getSearchFields', []);

        $this->assertSame(['foo', 'zoo'], $result);
    }

    /**
     * Test 'searchQuery' method, if search string is empty
     */
    public function testSearchQueryEmpty()
    {
        $document = $this->createPartialMock(TestDocumentBasic::class, []);
        $result = $this->callProtectedMethod($document, 'searchQuery', ['']);

        $this->assertSame([], $result);
    }

    /**
     * Provide data for testing 'searchQuery' method
     *
     * @return array
     */
    public function searchQueryProvider()
    {
        return [
            [
                'terms',
                ['foo'],
                [
                    '$and' => [
                        ['foo' => new \MongoDB\BSON\Regex('terms', 'i')]
                    ]
                ]
            ],
            [
                'some terms here',
                ['foo'],
                [
                    '$and' => [
                        ['foo' => new \MongoDB\BSON\Regex('some', 'i')],
                        ['foo' => new \MongoDB\BSON\Regex('terms', 'i')],
                        ['foo' => new \MongoDB\BSON\Regex('here', 'i')]
                    ]
                ]
            ],
            [
                'terms',
                ['foo', 'bar', 'zet'],
                [
                    '$and' => [
                        [
                            '$or' => [
                                ['foo' => new \MongoDB\BSON\Regex('terms', 'i')],
                                ['bar' => new \MongoDB\BSON\Regex('terms', 'i')],
                                ['zet' => new \MongoDB\BSON\Regex('terms', 'i')]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'some terms here',
                ['foo', 'bar', 'zet'],
                [
                    '$and' => [
                        [
                            '$or' => [
                                ['foo' => new \MongoDB\BSON\Regex('some', 'i')],
                                ['bar' => new \MongoDB\BSON\Regex('some', 'i')],
                                ['zet' => new \MongoDB\BSON\Regex('some', 'i')]
                            ]
                        ],
                        [
                            '$or' => [
                                ['foo' => new \MongoDB\BSON\Regex('terms', 'i')],
                                ['bar' => new \MongoDB\BSON\Regex('terms', 'i')],
                                ['zet' => new \MongoDB\BSON\Regex('terms', 'i')]
                            ]
                        ],
                        [
                            '$or' => [
                                ['foo' => new \MongoDB\BSON\Regex('here', 'i')],
                                ['bar' => new \MongoDB\BSON\Regex('here', 'i')],
                                ['zet' => new \MongoDB\BSON\Regex('here', 'i')]
                            ]
                        ],
                    ]
                ]
            ],
        ];
    }

    /**
     * Test 'searchQuery' method
     *
     * @dataProvider searchQueryProvider
     */
    public function testSearchQuery($terms, $searchFields, $expected)
    {
        TestDocumentBasic::$searchFields = $searchFields;

        $document = $this->createPartialMock(TestDocumentBasic::class, []);
        $result = $this->callProtectedMethod($document, 'searchQuery', [$terms]);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test 'searchQuery' method, if no search fields can be determined
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Unable to search: No search index fields defined
     */
    public function testSearchQueryException()
    {
        TestDocumentBasic::$searchFields = null;

        $document = $this->createPartialMock(TestDocumentBasic::class, []);
        $result = $this->callProtectedMethod($document, 'searchQuery', ['terms']);
    }

    /**
     * Provide data for testing 'search' method
     *
     * @return array
     */
    public function searchProvider()
    {
        return [
            [
                ['^foo_field'],
                [3, 1],
                [
                    'sort' => ['foo_field' => DB::DESCENDING],
                    'limit' => 3,
                    'skip' => 1
                ]
            ],
            [
                [],
                3,
                [
                    'sort' => ['id' => DB::ASCENDING],
                    'limit' => 3
                ]
            ],
            [
                ['^id'],
                null,
                [
                    'sort' => ['id' => DB::DESCENDING]
                ]
            ]
        ];
    }

    /**
     * Test 'search' method
     *
     * @dataProvider searchProvider
     */
    public function testSearch($sort, $limit, $findOpts)
    {
        $terms = 'terms';
        $filter = ['foo' => 'bar'];
        $query = [
            '$and' => [
                ['foo' => new \MongoDB\BSON\Regex('terms', 'i')]
            ],
            'foo' => 'bar'
        ];

        TestDocumentBasic::$searchFields = ['foo'];

        $cursor = $this->createMock(Cursor::class);

        $collection = $this->initCollection();
        $collection->expects($this->once())->method('find')->with($query, $findOpts)->willReturn($cursor);
        $collection->expects($this->once())->method('count')->with($query);

        $entitySet = $this->initEntitySet();

        $result = TestDocumentBasic::search($terms, $filter, $sort, $limit);

        $this->assertSame($entitySet, $result);
    }

    /**
     * Mock collection
     *
     * @return Collection
     */
    protected function initCollection()
    {
        TestDocumentBasic::$collection = 'test_collection';

        $collection = $this->createMock(Collection::class);

        $db = $this->createMock(DB::class);
        $db->expects($this->once())->method('selectCollection')->with('test_collection', ['documentClass' => TestDocumentBasic::class])->willReturn($collection);

        TestDocumentBasic::$connectionMock = $db;

        return $collection;
    }

    /**
     * Stub or mock EntitySet
     *
     * @return array
     */
    protected function initEntitySet()
    {
        TestDocumentBasic::$entitySetMock = [
            $this->createPartialMock(TestDocumentBasic::class, []),
            $this->createPartialMock(TestDocumentBasic::class, [])
        ];

        return TestDocumentBasic::$entitySetMock;
    }
}
