Jasny DB Mongo
===

[![Build Status](https://secure.travis-ci.org/jasny/db-mongo.png?branch=master)](http://travis-ci.org/jasny/db-mongo)

Jasny DB Mongo adds OOP design patterns to PHP's [Mongo extension](http://php.net/mongo).

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

Jasny DB Mongo is not a DB abstraction layer, it **extends** the Mongo classes. All of Mongo's classes, properties and
methods are available and will work as described in the PHP manual.

### Installation
The Jasny\DB library serves as an abstract base for concrete libraries implementing Jasny DB for specific
PHP extensions like mysqli and mongo. It isn't intended to be installed directly.
