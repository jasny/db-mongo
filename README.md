Jasny DB Mongo
===

[![Build Status](https://secure.travis-ci.org/jasny/db-mongo.png?branch=master)](http://travis-ci.org/jasny/db-mongo)

Jasny DB Mongo adds OOP design patterns to PHP's [MongoDB extension](https://php.net/manual/en/set.mongodb.php).

* [Named connections](#named-connections)
* [Entity](#entity)
* [Document object](#document-object)
* [Data mapper](#data-mapper)
* [Indexing](#indexing)
* [Metadata](#metadata)
* [Type casting](#type casting)
* [Validation](#validation)
* [Lazy loading](#lazy-loading)
* [Soft deletion](#soft-deletion)
* [Resultset](#resultset)
* [Maintainable code](#maintainable-code)

Jasny DB Mongo is not a DB abstraction layer, it **extends** the Mongo classes (except for `Jasny\DB\Mongo\Cursor` class, which incapsulates `MongoDB\Driver\Cursor`, but still allowing to call it's methods transparently). All of Mongo's classes, properties and
methods are available and will work as described in the PHP manual.

### Installation

Use [composer](https://getcomposer.org/) to install Jasny Mongo DB.

php composer.php require jasny/db-mongo '~2.0'

### Usage

It can be used in the same way as MongoDB extension, with a few improvements.

---

#### Init connection

When creating Database instance, we can use not only `MongoDB\Driver\Manager` as first parameter, but also an array of options, or even a uri connection string:

```

$options = [
    'client' => 'mongodb://localhost:27017',
    'database' => 'test'
];

$db = new DB($options, '');

```

or

```

$uri = 'mongodb://user:password@test-host:27017/test-db?foo=bar';
$db = new DB($uri, '');

```

Or database name can be passed as second parameter, like it is required in `MongoDB\Database`.

---

#### Saving items

You can use collection save methods, defined in `MongoDB\Collection`. Unlike in old [PHP Mongo](https://php.net/manual/en/book.mongo.php) extension, `save` method is removed from new extension. Our library implements it in `Jasny\DB\Mongo\Collection` class, as it is very handy.

So you can do:

```

$collection = $db->test_collection;
$document = ['foo' => 'bar'];

$collection->save($document);

```

After this `$document` will contain `_id` field.

If you use other saving methods like `replaceOne`, `insertOne`, `insertMany`, id field is not automatically attached to document, as we need to follow parent methods declaration, which does not allow this.

To assign created id to document, you can do:

```

$result = $collection->insertOne($document);
$collection->useResultId($document, '_id', $result);

```

That is automatically performed in some of our library classes, like `Jasny\DB\Mongo\DataMapper` or `Jasny\DB\Mongo\Document`.

---

#### Fetching from db

When using `$cursor = $collection->find($filter)` for fetching records, an instance of `Jasny\DB\Mongo\Cursor` is returned. It does not extend `MongoDB\Driver\Cursor` class, as all `MongoDB\Driver` classes are final. Instead it incapsulates it, implementing magical calling of all it's methods.

So the following check won't work:

```

$cursor instanceof MongoDB\Driver\Cursor; // false

```

But you still can do everything else:

````

$asArray = $cursor->asArray();

````

or performing `foreach` iteration over cursor.

---

#### Cast fetched records

There is a base class we use for items, that are stored in DB. That's `Jasny\DB\Entity`, that is defined in [Jasny DB](https://github.com/jasny/db) repository.

Fetching db records using collection methods `find` and `findOne` can produce records of this class (or any of it subclasses you define).

To make use of that, you should obtain collection instance in the following way:

```

$collection = $db->selectCollection('foo_collection', ['documentClass' => SomeEntity::class]);

```

Casting to `Entity` class is performed by our framework, without use of `MongoDB` `typeMap` functionality, because casting can be pretty complex.

Than in the following case:

```

$record = $collection->findOne($filter);

```

`$record` is an instance of `Jasny\DB\Entity`.

In the following case:

```

$cursor = $collection->find($filter);

```

to obtain casted records, you should either iterate over `$cursor` using `foreach`, or use `$cursor->toArrayCast()` method, implemented by our framework. If you use native `$cursor->toArray()` method, implemented by `MongoDB\Driver\Cursor`, records use casting defined in collection's `typeMap` option. By default we set this option to use casting to array.
