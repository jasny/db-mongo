<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\Functional;

use Improved as i;
use Jasny\DB\FieldMap\ConfiguredFieldMap;
use Jasny\DB\Filter\FilterItem;
use Jasny\DB\Mongo\Query\FilterQuery;
use Jasny\DB\Mongo\Reader;
use Jasny\DB\Mongo\Writer;
use Jasny\DB\Option as opts;
use Jasny\DB\QueryBuilder\FilterQueryBuilder;
use Jasny\DB\Result\ResultBuilder;
use MongoDB\BSON\ObjectId;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Test against the zips collection.
 *
 *   wget http://media.mongodb.org/zips.json
 *   mongoimport -v --file=zips.json
 *
 * @coversNothing
 */
class ZipTest extends TestCase
{
    protected Database $db;
    protected Collection $collection;
    protected Reader $reader;
    protected Writer $writer;

    protected Logger $logger;

    public function setUp(): void
    {
        $this->db = (new Client())->test;

        if (i\iterable_count($this->db->listCollections(['filter' => ['name' => 'zips']])) === 0) {
            $this->markTestSkipped("The 'zips' collection isn't present in the 'test' db");
        }

        $typeMap = ['array' => 'array', 'document' => 'array', 'root' => 'array'];
        $this->collection = $this->db->selectCollection('zips', ['typeMap' => $typeMap]);

        if (in_array('write', $this->getGroups(), true)) {
            $this->cloneCollection(); // Only clone if data is changed as cloning slows down the tests.
        }

        $this->logger = constant('PHPUNIT_FUNCTIONAL_DEBUG') === 'on'
            ? new Logger('MongoDB', [new StreamHandler(STDERR)])
            : new Logger('MongoDB', [new NullHandler()]);

        $map = new ConfiguredFieldMap(['_id' => 'id']);

        $this->reader = Reader::basic($map)->forCollection($this->collection)->withLogging($this->logger);
        $this->writer = Writer::basic($map)->forCollection($this->collection)->withLogging($this->logger);
    }

    public function tearDown(): void
    {
        if (isset($this->db) && $this->db->getDatabaseName() === 'test_jasnydb') {
            $this->db->drop();
        }
    }

    protected function cloneCollection(): void
    {
        $name = $this->collection->getCollectionName() . '_copy';
        $this->collection->aggregate([['$match' => (object)[]], ['$out' => $name]]);

        $this->collection = $this->db->selectCollection($name, ['typeMap' => $this->collection->getTypeMap()]);
    }


    public function testCount()
    {
        $this->assertEquals(29353, $this->reader->count());
        $this->assertEquals(1595, $this->reader->count(['state' => "NY"]));
    }

    public function testFetchFirst()
    {
        $location = $this->reader->fetch(['id' => "01008"])->first();

        $this->assertEquals([
            "id" => "01008",
            "city" => "BLANDFORD",
            "loc" => [-72.936114, 42.182949],
            "pop" => 1240,
            "state" => "MA"
        ], $location);
    }

    public function testFetchLimit()
    {
        $expected = [
            ["id" => "06390", "city" => "FISHERS ISLAND"],
            ["id" => "10001", "city" => "NEW YORK"],
            ["id" => "10002", "city" => "NEW YORK"],
            ["id" => "10003", "city" => "NEW YORK"],
            ["id" => "10004", "city" => "GOVERNORS ISLAND"],
        ];

        $result = $this->reader->fetch(
            ['state' => "NY"],
            [opts\limit(5), opts\sort('id'), opts\fields('id', 'city')]
        );

        $locations = i\iterable_to_array($result);

        $this->assertEquals($expected, $locations);
    }

    /**
     * Add a custom filter to support `(near)` as operator.
     *
     * Using $geoWithin instead of $near, because of countDocuments.
     * @see https://docs.mongodb.com/manual/reference/method/db.collection.countDocuments/#query-restrictions
     */
    public function testNearFilter()
    {
        $this->collection->createIndex(['loc' => "2d"]);

        $queryBuilder = $this->reader->getQueryBuilder()
            ->withCustomOperator(
                'near',
                static function (FilterQuery $query, FilterItem $filter, array $opts) {
                    [$field, $value] = [$filter->getField(), $filter->getValue()];
                    $dist = opts\setting('near', 1.0)->findIn($opts);

                    $query->add([$field => ['$geoWithin' => ['$center' => [$value, $dist]]]]);
                }
            );

        $this->reader = (new Reader($queryBuilder, $this->reader->getResultBuilder()))
            ->forCollection($this->collection);

        $filter = ['loc(near)' => [-72.622739, 42.070206]];

        $this->assertEquals(445, $this->reader->count($filter));
        $this->assertEquals(14, $this->reader->count($filter, [opts\setting('near', 0.1)]));
    }

    public function testFetchWithCustomFieldMap()
    {
        $expected = [
            ["zipcode" => "06390", "city" => "FISHERS ISLAND"],
            ["zipcode" => "10001", "city" => "NEW YORK"],
            ["zipcode" => "10002", "city" => "NEW YORK"],
            ["zipcode" => "10003", "city" => "NEW YORK"],
            ["zipcode" => "10004", "city" => "GOVERNORS ISLAND"],
        ];

        $fieldMap = new ConfiguredFieldMap([
            '_id' => 'zipcode',
            'city' => 'city',
            'state' => 'area'
        ]);

        $this->reader = Reader::basic($fieldMap)->forCollection($this->collection);

        $result = $this->reader->fetch(
            ['area' => "NY"],
            [opts\limit(5), opts\sort('zipcode'), opts\omit('area', 'loc', 'pop')]
        );

        $locations = i\iterable_to_array($result);
        $this->assertEquals($expected, $locations);
    }

    /**
     * @group write
     */
    public function testSaveAll()
    {
        $locations = [
            ["id" => "90208", "city" => "BLUE HILLS", "loc" => [-118.406477, 34.092], "state" => "CA"],
            ["id" => "90209", "city" => "BLUE HILLS", "loc" => [-118.407, 34.0], "pop" => 99, "state" => "CA"],
        ];

        $index = 0;
        $result = $this->writer->saveAll($locations);

        foreach ($result as $i => $document) {
            $location = $locations[$i];
            //$this->assertEquals($locations[$index++], $location);
            //$this->assertEquals(['id' => $location['id']], $document);

            $expected = ['_id' => $location['id']] + array_diff_key($location, ['id' => 0]);
            $this->assertInMongoCollection($expected, $document['id']);
        }
    }

    /**
     * @group write
     */
    public function testSaveAllWithGeneratedIds()
    {
        $this->markTestSkipped();

        $locations = [
            ["city" => "BLUE HILLS", "loc" => [-118.406477, 34.092], "state" => "CA"],
            ["city" => "BLUE HILLS", "loc" => [-118.407, 34.0], "pop" => 99, "state" => "CA"],
        ];

        $index = 0;
        $result = $this->writer->saveAll($locations);

        foreach ($result as $location => $document) {
            $this->assertSame(['id'], array_keys($document));
            $this->assertInstanceOf(ObjectId::class, $document['id']);

            $this->assertEquals($locations[$index++], $location);

            $expected = ['_id' => $document['id']] + $location;
            $this->assertInMongoCollection($expected, $document['id']);
        }
    }

    /**
     * @group write
     */
    public function testSaveAllWithCustomFieldMap()
    {
        $this->markTestSkipped();

        $fieldMap = new ConfiguredFieldMap([
            '_id' => 'id',
            'city' => 'city',
            'loc' => 'latlon',
            'state' => 'area'
        ]);

        $this->writer = $this->writer
            ->withSaveQueryBuilder(new SaveQueryBuilder($fieldMap))
            ->withResultBuilder(new ResultBuilder($fieldMap));

        $locations = [
            ["city" => "BLUE HILLS", "latlon" => [-118.406477, 34.092], "area" => "CA"],
            ["city" => "BLUE HILLS", "latlon" => [-118.407, 34.0], "pop" => 99, "area" => "CA", "bar" => 'r'],
        ];

        $index = 0;
        $result = $this->writer->saveAll($locations);

        foreach ($result as $location => $document) {
            $this->assertSame(['id'], array_keys($document));
            $this->assertInstanceOf(ObjectId::class, $document['id']);

            $this->assertEquals($locations[$index++], $location);

            $expected = [
                '_id' => $document['id'],
                'city' => $location['city'],
                'loc' => $location['latlon'],
                'state' => $location['area'],
            ];
            $this->assertInMongoCollection($expected, $document['id']);
        }
    }

    protected function assertInMongoCollection($expected, $id)
    {
        $found = $this->collection->findOne(['_id' => $id]);

        if (!$found) {
            $this->fail("No document found with id '$id' in MongoDB collection");
            return;
        }

        $this->assertEquals($expected, $found);
    }
}