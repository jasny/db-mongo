Jasny DB Mongo
========

[![Build Status](https://secure.travis-ci.org/jasny/db-mongo.png?branch=master)](http://travis-ci.org/jasny/db-mongo)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jasny/db-mongo/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jasny/db-mongo/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jasny/db-mongo/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jasny/db-mongo/?branch=master)
[![Packagist Stable Version](https://img.shields.io/packagist/v/jasny/db-mongo.svg)](https://packagist.org/packages/jasny/db-mongo)
[![Packagist License](https://img.shields.io/packagist/l/jasny/db-mongo.svg)](https://packagist.org/packages/jasny/db-mongo)

MongoDB implementation of [Jasny DB](https://github.com/jasny/db).

Jasny DB is a database abstraction layer that doesn't wrap and shield underlying the PHP driver.

The library provides services that allow the user to abstract DB access, while still having access to all capabilities
of the underlying db / storage.

_All objects are immutable._

This library doesn't do ORM / ODM. For that take a look at [Jasny Entity Mapper](https://github.com/jasny/entity-mapper)
and [Jasny DB Gateway](https://github.com/jasny/db-mongo-gateway).

Installation
---

    composer require jasny/db-mongo

Usage
---

#### Fetch a list

```php
use Jasny\DB\Option as opts;
use Jasny\DB\Mongo\Read\MongoReader;

$users = (new MongoDB\Client)->test->users;
$reader = new MongoReader();

$list = $reader
    ->fetch(
        $users,
        [
            'invite.group' => 'A team',
            'activation_date (min)' => new DateTime(),
            'role (any)' => ['user', 'admin', 'super']
        ],
        [
            opts\fields('name', 'role', 'age'),
            opts\limit(10)
        ]
    )
    ->map(function(array $user): string {
        return sprintf("%s (%s) - %s", $user['name'], $user['age'], $user['role']);
    });
```

#### Read and write

```php
use Jasny\DB\Mongo\Read\MongoReader;
use Jasny\DB\Mongo\Write\MongoWriter;

$users = (new MongoDB\Client)->test->users;
$reader = new MongoReader();
$writer = new MongoWriter();

$user = $reader->fetch($users, ['id' => '12345'])->first();
$user->count = "bar";

$writer->save($users, [$user]);
```

#### Update multiple

```php
use Jasny\DB\Mongo\Write\MongoWriter;

$users = (new MongoDB\Client)->test->users;
$writer = new MongoWriter;

$writer->update($users, (object)['access' => 1000], ['type' => 'admin']);
```

_**Jasny DB makes extensive use of iterators and generators.** It's important to understand what they are and how they
work. If you're not familiar with this concept, first read
"[What are iterators?](https://github.com/improved-php-library/iterable#what-are-iterators)"._

## Filters

The reader and writer methods accept a `$filter` argument. The filter is an associated array with field key and
corresponding value.

A filter SHOULD always result in the same or a subset of the records you'd get when calling the method without a
filter.

```php
$zoo = $reader->fetch($storage, ['reference' => 'zoo'])->first();
$count = $reader->count($storage, ['bar' => 10]);
$writer->update($storage, (object)['access' => 1000], ['type' => 'admin']);
```

Filter keys may include an operator. The following operator are supported by default

Key            | Value  | Description
-------------- | ------ | ---------------------------------------------------
"field"        | scalar | Field is the value
"field (not)"  | scalar | Field is not the value
"field (min)"  | scalar | Field is equal to or greater than the value
"field (max)"  | scalar | Field is equal to or less than the value
"field (any)"  | array  | Field is one of the values in the array
"field (none)" | array  | Field is none of the values in the array

If the field is an array, you may use the following operators

Key            | Value  | Description
-------------- | ------ | ---------------------------------------------------
"field"        | scalar | The value is part of the field
"field (not)"  | scalar | The value is not part of the field
"field (any)"  | array  | Any of the values are part of the field
"field (all)"  | array  | All of the values are part of the field
"field (none)" | array  | None of the values are part of the field

To filter between two values, use both `(min)` and `(max)`.

For data stores that support structured data (as MongoDB) the field may use the dot notation to reference a deeper
properties.

_The filter is a simple associative array, rather than an array of objects, making it easier to pass (part of) the
HTTP query parameters as filter._

## Options

In additions to a filter, database specific options can be passed. Such options include limiting the number of results,
loading related data, sorting, etc. These options are passed to the query builder.

```php
use Jasny\DB\Option as opts;
use Jasny\DB\Mongo\Read\MongoReader;

$users = (new MongoDB\Client)->test->users;
$reader = new MongoReader;

$list = $reader
    ->fetch(
        $users,
        ['active' => true],
        [
            opts\fields('name', 'role', 'age'),
            opts\limit(10),
            opts\sort('~activation_date', 'name')
        ]
    );
```

This library defines the concept of options and a number of common options.

* `fields(string ...$fields)`
* `omit(string ...$fields)`
* `sort(string ...$fields)`
* `limit(int limit, int offset = 0)`
* `page(int pageNr, int pageSize)` _(pagination is 1-indexed)_

For sorting, add a `~` in front of the field to sort in descending order.

Jasny DB implementations may define additional options.

## Read service

The `MongoReader` service can be used to fetch data from a collection. The `MongoDB\Collection` object is not embedded
to the service, but instead passed to it when calling a method.

The reader converts the [generic filter](#filters) into a MongoDB filter and wraps the query result in an iterator
pipeline.

The result pipeline renames the `_id` field `id`. Also, BSON type objects are cast to regular PHP types.

The `fetch` and `count` methods accept a filter and options (`$opts`). The following options can be used: `fields`,
`omit`, `sort` `limit` and `page`.

### fetch

    Result fetch(MongoDB\Collection $storage, array $filter, array $opts = [])

Query and fetch data.

This maps to the `MongoDB\Collection::find()`.

### count

    int count(MongoDB\Collection $storage, array $filter, array $opts = [])
    
Query and count result.

This maps to the `MongoDB\Collection::count()`.

### Result

The `MongoReader::fetch()` method returns a `Result` object which extends
[iterator pipeline](https://github.com/improved-php-library/iteratable). As such, it provides methods, like map/reduce,
to further process the result.

```php
use Improved\IteratorStream\CsvOutputStream;
use Jasny\DB\Option as opts;
use Jasny\DB\Mongo\Read\MongoReader;

$users = (new MongoDB\Client)->test->users;
$reader = new MongoReader;

$handler = fopen('path/to/export/invited.csv');
$outputToCSV = new CsvOutputStream($handler);

$reader
    ->fetch(
        $users,
        ['member_since(max)' => new \DateTime('-1 year')],
        [opts\fields('name', 'age', 'email'), opts\omit('id')]
    ])
    ->project(['name', 'age', 'email'])
    ->then($outputToCSV);
```

By default the results do not hold any meta data.

## Write service

The `MongoWriter` service is used to save, update and delete data of a collection. Similar to the read service,
the `MongoDB\Collection` object needs to be passed to each method.

The writer service converts the [generic filter](#filters) into a MongoDB specific query. It also converts PHP types
to BSON specific types (eg `MongoDB\BSON\UTCDateTime` to `DateTime`).

The `save`, `update` and `delete` methods accept options (`$opts`).

### save

    iterable save(MongoDB\Collection $storage, iterable $documents, array $opts = [])

Save the documents. If a document has a unique id update it, otherwise add it.

Multiple documents must be specified. If you only want to save one document, wrap it in an array as
`save($storage, [$document])`.

All document that don't have an id, will be automatically inserted. Other documents will be updated, or upserted if
they don't exist.

The method returns sparse documents with only the `id` property for inserted and upserted documents. 

The following meta data is available

* `"count": int` - Total number of documents inserted or updated.
* `"deletedCount": int` - Typically 0
* `"insertedCount": int`
* `"matchedCount": int`
* `"modifiedCount": int`
* `"upsertedCount": int`
* `"acknowledged": bool`

### update

    void update($storage, array $filter, UpdateOperation|UpdateOperation[] $changes,, array $opts = [])
    
Query and update records.

```php
use Jasny\DB\Update as update;
use Jasny\DB\Mongo\Write\MongoWriter;

$writer = new MongoWriter();
$users = (MongoDB\Client)->tests->users;

$writer->update($users, ['id' => 10], [update\set('last_login', new DateTime()), update\inc('logins')]);
```

The `$changes` argument must be one or more `UpdateOperation` objects. Rather than creating such an object by hand, the
following helper functions exist in the `Jasny\DB\Update` namespace:

* `set(string $field, $value)` or `set(iterable $values)`
* `patch(string $field, array|object $value)`
* `inc(string $field, int|float value = 1)`
* `dec(string $field, int|float value = 1)`
* `mul(string $field, int|float value)`
* `div(string $field, int|float value)`

If the field is an array, the following operations are also available
* `add(string $field, $value)`
* `rem(string $field, $value)`

The `mod()` operation is not supported by MongoDB and can therefor not be used.

To prevent accidentally swapping the changes and filter, passing a normal associative array is not allowed. Instead use
`update\set($values)`, where values are all values that need to be set.

If you want to update every record of the storage (table, collection, etc) you have to supply an empty array as filter.

To update a single document, you can pass `opt\limit(1)`. Trying to set a limit that isn't exactly one will lead to an
error.

### delete

    void delete(MongoDB\Collection $storage, array $filter, array $opts = [])
    
Query and delete records.

If you want to update every record of the storage (table, collection, etc) you have to supply an empty array as filter.

To update a single document, you can pass `opt\limit(1)`. Trying to set a limit that isn't exactly one will lead to an
error.

## Customization

`MongoReader::withQueryBuilder()` and `MongoWriter::withQueryBuilder()` allows using a custom query builder. This
service converts the generic filter to a MongoDB query.

`MongoWriter` also has a `withSaveQueryBuilder()` and `withUpdateQueryBuilder()` method.

`MongoReader::withResultBuilder()` and `MongoWriter::withResultBuilder()` allows setting a custom result builder with
additional or customized steps.

See the [Jasny DB README](https://github.com/jasny/db) for detailed information about customizing the read service,
query builder and result builder.

### Type conversion

**TODO**

### Model

**TODO**
