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
use Jasny\DB\Update as update;
use MongoDB\BSON\ObjectId;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use function Jasny\array_without;

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

        $this->logger = getenv('FUNCTIONAL_TESTS_DEBUG') === 'on'
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
            ->forCollection($this->collection)
            ->withLogging($this->logger);

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

        $this->reader = Reader::basic($fieldMap)->forCollection($this->collection)->withLogging($this->logger);

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
            'a' => ["id" => "90208", "city" => "BLUE HILLS", "loc" => [-118.406477, 34.092], "state" => "CA"],
            '2' => ["id" => "90209", "city" => "BLUE HILLS", "loc" => [-118.407, 34.0], "pop" => 99, "state" => "CA"],
        ];

        $result = $this->writer->saveAll($locations);

        foreach ($result as $key => $document) {
            $location = $locations[$key];

            $expected = ['_id' => $location['id']] + array_diff_key($location, ['id' => 0]);
            $this->assertInMongoCollection($expected, $document['id']);
        }
    }

    /**
     * @group write
     */
    public function testSaveAllWithGeneratedIds()
    {
        $locations = [
            'a' => (object)["city" => "BLUE HILLS", "loc" => [-118.406477, 34.092], "state" => "CA"],
            '2'  => (object)["city" => "BLUE HILLS", "loc" => [-118.407, 34.0], "pop" => 99, "state" => "CA"],
        ];

        $result = $this->writer->saveAll($locations, [opts\apply_result()]);

        foreach ($result as $key => $location) {
            $this->assertArrayHasKey($key, $locations);
            $this->assertSame($locations[$key], $location);
            $this->assertObjectHasAttribute('id', $location);
            $this->assertInstanceOf(ObjectId::class, $location->id);

            $expected = ['_id' => $location->id] + array_diff_key((array)$location, ['id' => 0]);
            $this->assertInMongoCollection($expected, $location->id);
        }
    }

    /**
     * @group write
     */
    public function testSaveAllWithCustomFieldMap()
    {
        $fieldMap = new ConfiguredFieldMap([
            '_id' => 'ref',
            'city' => 'city',
            'loc' => 'latlon',
            'state' => 'area'
        ]);

        $this->writer = Writer::basic($fieldMap)->forCollection($this->collection)->withLogging($this->logger);

        $locations = [
            'a' => (object)["city" => "BLUE HILLS", "latlon" => [-118.406477, 34.092], "area" => "CA"],
            '2' => (object)["city" => "BLUE HILLS", "latlon" => [-118.407, 34.0], "pop" => 99, "area" => "CA",
                "bar" => 'r'],
        ];

        $result = $this->writer->saveAll($locations, [opts\apply_result()]);

        foreach ($result as $key => $location) {
            $this->assertArrayHasKey($key, $locations);
            $this->assertSame($locations[$key], $location);
            $this->assertObjectHasAttribute('ref', $location);
            $this->assertInstanceOf(ObjectId::class, $location->ref);

            $expected = [
                '_id' => $location->ref,
                'city' => $location->city,
                'loc' => $location->latlon,
                'state' => $location->area,
            ] + array_without((array)$location, ['ref', 'city', 'latlon', 'area']);

            $this->assertInMongoCollection($expected, $location->ref);
        }
    }


    /**
     * @group write
     */
    public function testUpdate()
    {
        $this->writer->update(["_id" => "10004"], update\set(["city" => "NEW YORK"]));

        $expected = [
            '_id' => "10004",
            'city' => "NEW YORK",
            'loc' => [-74.019025, 40.693604],
            'pop' => 3593,
            'state' => "NY",
        ];

        $this->assertInMongoCollection($expected, "10004");
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

    protected function assertNotInMongoCollection($id)
    {
        $found = $this->collection->findOne(['_id' => $id]);
        $this->assertNull($found);
    }
}
