Jasny DB Mongo Changelog
===

#v2.0.0 (upcoming)

- Removed usage of obsolete [PHP Mongo](https://php.net/manual/en/book.mongo.php) extension
- Implemented usage of [PHP MongoDB](https://php.net/manual/en/set.mongodb.php) extension, it's base classes are extended by Jasny DB Mongo classes:
    - `Jasny\DB\Mongo\DB` extends `MongoDB\Database`
    - `Jasny\DB\Mongo\Collection` extends `MongoDB\Collection`
- `Jasny\DB\Mongo\Cursor` does not extend `MongoDB\Driver\Cursor` class, because all `MongoDB\Driver` classes are final. But it incapsulates it's instance in a way, that all driver cursor methods can still be called, as if it was extended.
- `Jasny\DB\Mongo\DB` now does not implement `Jasny\DB\Connection` and `Jasny\DB\Connection\Namable` interfaces, because it's contructor definition does not equals to the one in `Jasny\DB\Connection` interface.
