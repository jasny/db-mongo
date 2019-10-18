<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Tests\Functional;

use Improved as i;
use Jasny\DB\FieldMap\ConfiguredFieldMap;
use Jasny\DB\Mongo\Query\FilterQuery;
use Jasny\DB\Mongo\QueryBuilder\FilterQueryBuilder;
use Jasny\DB\Mongo\QueryBuilder\SaveQueryBuilder;
use Jasny\DB\Mongo\Read\Reader;
use Jasny\DB\Mongo\Result\ResultBuilder;
use Jasny\DB\Mongo\Write\Writer;
use Jasny\DB\Option as opts;
use Jasny\DB\QueryBuilder\Compose\CustomFilter;
use MongoDB\BSON\ObjectId;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
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

    public function setUp(): void
    {
        $this->db = (new Client())->test;

        if (i\iterable_count($this->db->listCollections(['filter' => ['name' => 'zips']])) === 0) {
            $this->markTestSkipped("The 'zips' collection isn't present in the 'test' db");
        }

        $typeMap = ['array' => 'array', 'document' => 'array', 'root' => 'array'];
        $this->collection = $this->db->selectCollection('zips', ['typeMap' => $typeMap]);

        if (in_array('write', $this->getGroups(), true)) {
            $this->cloneCollection();
        }

        $this->reader = new Reader($this->collection);
        $this->writer = new Writer($this->collection);

        if (constant('PHPUNIT_FUNCTIONAL_DEBUG') === 'on') {
            $logger = new Logger('MongoDB', [new StreamHandler(STDERR)]);
            $this->reader = $this->reader->withLogging($logger);
            $this->writer = $this->writer->withLogging($logger);
        }
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
            [opts\limit(5), opts\sort('id'), opts\fields('city')]
        );

        $locations = i\iterable_to_array($result);

        $this->assertEquals($expected, $locations);
    }

    /**
     * Add a custom filter to support `(near)` as operator.
     * The operator optionally takes a max distance as `(near:<float>)`.
     *
     * Using $geoWithin instead of $near, because of countDocuments.
     * @see https://docs.mongodb.com/manual/reference/method/db.collection.countDocuments/#query-restrictions
     */
    public function testNearFilter()
    {
        $this->collection->createIndex(['loc' => "2d"]);

        $queryBuilder = (new FilterQueryBuilder())
            ->onCompose(new CustomFilter(
                static function (string $field, ?string $operator) {
                    return (bool)preg_match('/^near(:|$)/', (string)$operator);
                },
                static function (FilterQuery $query, string $field, string $operator, $value, array $opts) {
                    [, $dist] = explode(':', $operator) + [1 => 1.0];
                    $query->add([$field => ['$geoWithin' => ['$center' => [$value, (float)$dist]]]]);
                }
            ));

        $this->reader = $this->reader->withQueryBuilder($queryBuilder);

        $this->assertEquals(445, $this->reader->count(['loc(near)' => [ -72.622739, 42.070206]]));
        $this->assertEquals(14, $this->reader->count(['loc(near:0.1)' => [ -72.622739, 42.070206]]));
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
        ], false);

        $this->reader = $this->reader
            ->withQueryBuilder(new FilterQueryBuilder($fieldMap))
            ->withResultBuilder(new ResultBuilder($fieldMap));

        $result = $this->reader->fetch(
            ['area' => "NY"],
            [opts\limit(5), opts\sort('zipcode'), opts\omit('area')]
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

        foreach ($result as $location => $document) {
            $this->assertEquals(['id' => $location['id']], $document);
            $this->assertEquals($locations[$index++], $location);

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
        $fieldMap = new ConfiguredFieldMap([
            '_id' => 'id',
            'city' => 'city',
            'loc' => 'latlon',
            'state' => 'area'
        ], false);

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
