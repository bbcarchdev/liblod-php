# liblod-php

A Linked Open Data client library for PHP, developed as part of the
[RES project](http://res.space/).

It also works for Linked Data (without the "Open"), but was initially
developed for LOD, and the name stuck.

**Note that this is experimental code which is still under development.**

## Requirements

PHP 5.6 or higher (it works under PHP 7, but no features from PHP 7 are used).

## Installation

To install this library for use with your own code, do:

```
composer require bbcarchdev/liblod
```

## Usage

See `liblod-php_usage.md` (in this directory).

## Developing the code

To develop liblod-php, clone the repo:

```
git clone https://github.com/bbcarchdev/liblod-php.git liblod-php
cd liblod-php
```

Then you'll need to install the dependencies. You can do this with:

```
php tools/composer.phar install
```

## Running the tests

To run the unit tests:

```
./vendor/bin/robo unit
```

To run the integration tests:

```
./vendor/bin/robo int
```

**Note that the integration tests work against the live [Acropolis stack](http://acropolis.org.uk/) and other LOD sites, so you will need a network connection to run them. They can also be somewhat fragile, as the number of statements for fetched resources may periodically change, depending on what has been ingested. This can occasionally cause test failures.**

## Code coverage

To generate a code coverage report for the tests, you will first need to [install the XDebug PHP module](https://xdebug.org/docs/install). Then, run:

```
./vendor/bin/robo cov
```

The report can be viewed by opening `build/cov/index.html` in a web browser.

## Code quality

Code quality checks can be run with:

```
./vendor/bin/robo mess
```

This uses [PHPMD](https://phpmd.org/) to report on various issues with the code.

## API docs

Rudimentary (incomplete) API docs can be generated with:

```
./vendor/bin/robo docs
```

The generated docs end up in the `build/apidocs/` directory.

## Code style checking

The code style can be checked with:

```
./vendor/bin/robo style
```

The code style report ends up in the `build/style/` directory.

(Note that the code style configuration is in the `phpcheckstyle-config.xml` file.)

## Authors

API design by [Mo McRoberts](https://github.com/nevali).

Implementation by [Elliot Smith](https://github.com/townxelliot).

## Contributing

Contributions are welcome via [github pull requests](https://github.com/bbcarchdev/liblod-php).

Please use the [github issue tracker](https://github.com/bbcarchdev/liblod-php/issues) to raise issues.

## Licence

Elliot Smith, © BBC 2017

liblod-php is licensed under the terms of the Apache License, Version 2.0
(see LICENCE-APACHE.txt).

The liblod-php code base distributes the following software (used during development):

* [Composer](http://getcomposer.org/): distributed under the [MIT licence](https://opensource.org/licenses/MIT). See `tools/LICENCE-COMPOSER-MIT.txt` for the full licence.
* [PHPUnit](http://phpunit.de/): distributed under the [3-clause BSD licence](https://opensource.org/licenses/BSD-3-Clause). See `tools/LICENCE-PHPUNIT-BSD3.txt` for the full licence.
* [phpDocumentor](https://www.phpdoc.org/): distributed under the [MIT licence](https://github.com/phpDocumentor/phpDocumentor2/blob/develop/LICENSE). See `tools/LICENCE-PHPDOCUMENTOR-MIT.txt` for the full licence.

(NB these libraries are distributed with the source because they cause version clashes with dependencies used by the runtime library or are inconvenient to install.)

liblod-php depends on these libraries at runtime (which are licensed as stated); these are not distributed with liblod-php:

* [pietercolpaert/hardf](https://github.com/pietercolpaert/hardf) - [MIT licence](https://github.com/pietercolpaert/hardf/blob/master/LICENSE)
* [easyrdf/easyrdf](http://easyrdf.org/) - [BSD 3-clause licence](https://github.com/njh/easyrdf/blob/master/LICENSE.md)
* [guzzlehttp/psr7](http://guzzlephp.org/) - [MIT licence](https://github.com/guzzle/guzzle/blob/master/LICENSE)
* [guzzlehttp/guzzle](http://guzzlephp.org/) - [MIT licence](https://github.com/guzzle/guzzle/blob/master/LICENSE)

liblod-php depends on these libraries for development (which are licensed as stated); these are not distributed with liblod-php:

* [consolidation/robo](https://github.com/consolidation/robo) - [MIT licence](https://github.com/consolidation/Robo/blob/master/LICENSE)
* [phpmd/phpmd](https://phpmd.org/) - [BSD 3-clause licence](https://github.com/phpmd/phpmd/blob/master/LICENSE)
* [phpcheckstyle/phpcheckstyle](https://github.com/PHPCheckstyle/phpcheckstyle) - [Open Software Licence](https://github.com/PHPCheckstyle/phpcheckstyle/blob/master/LICENSE.txt)
