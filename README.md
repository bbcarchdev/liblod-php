# res/liblod

A Linked Open Data library for PHP, developed as part of the
[RES project](http://res.space/).

**Note that this is experimental code which is still under development.**

The `build.sh` script provides shortcuts for the commands shown below, but
is only tested on Mac and Linux. If you're working on Windows, you'll need the
commands below in full.

## Requirements

PHP 5.4 or higher (it works under PHP 7, but no features from PHP 7 are used).

## Installation

```
composer require res/liblod
```

## Usage

See `res_liblod_usage.md` (in this directory).

## Developing the code

To develop res/liblod, you'll need to install the dependencies. You can do this
with:

```
# shortcut
./build.sh install

# full
php tools/composer.phar install
```

## Running the tests

To run the unit tests:

```
# shortcut
./build.sh test-unit
OR
./build.sh

# full
php tools/phpunit.phar --bootstrap vendor/autoload.php tests/unit
```

To run the integration tests:

```
# shortcut
./build.sh test-int

# full
php tools/phpunit.phar --bootstrap vendor/autoload.php tests/integration
```

**Note that the integration tests work against the live [Acropolis stack](http://acropolis.org.uk/) and other LOD sites, so you will need a network connection to run them. They can also be somewhat fragile, as the number of statements for Acropolis resources may periodically change, depending on what's being ingested. This can cause occasionally cause test failures.**

## Code coverage

To generate a code coverage report for the tests, you will need to [install XDebug](https://xdebug.org/docs/install). Then, run:

```
# shortcut
./build.sh cov

# full
php tools/phpunit.phar --bootstrap vendor/autoload.php --coverage-html cov --whitelist src tests/integration
```

The report can be viewed by opening `cov/index.html` in a web browser.

## Code quality

Code quality checks can be run with:

```
# shortcut
./build.sh mess

# full
php vendor/bin/phpmd src text cleancode,codesize,design,naming,unusedcode
```

This uses [PHPMD](https://phpmd.org/) to report on various issues with the code.

## Authors

API design by [Mo McRoberts](https://github.com/nevali).

Implementation by [Elliot Smith](https://github.com/townxelliot).

## Contributing

???

## Licence

Copyright © 2017 BBC

res/liblod is licensed under the terms of the Apache License, Version 2.0
(see LICENCE-APACHE.txt).

The res/liblod code base distributes the following software:

* [PHPUnit](http://phpunit.de/): distributed under the [3-clause BSD licence](https://opensource.org/licenses/BSD-3-Clause). See `tools/LICENCE-PHPUNIT-BSD3.txt` for the full licence.
* [Composer](http://getcomposer.org/): distributed under the [MIT licence](https://opensource.org/licenses/MIT). See `tools/LICENCE-COMPOSER-MIT.txt` for the full licence.

res/liblod depends on these libraries (which are licensed as stated):

* [pietercolpaert/hardf](https://github.com/pietercolpaert/hardf) - [MIT licence](https://github.com/pietercolpaert/hardf/blob/master/LICENSE)
* [easyrdf/easyrdf](http://easyrdf.org/) - [BSD 3-clause licence](https://github.com/njh/easyrdf/blob/master/LICENSE.md)
* [guzzlehttp/psr7](http://guzzlephp.org/) - [MIT licence](https://github.com/guzzle/guzzle/blob/master/LICENSE)
* [guzzlehttp/guzzle](http://guzzlephp.org/) - [MIT licence](https://github.com/guzzle/guzzle/blob/master/LICENSE)
* [phpmd/phpmd](https://phpmd.org/) - [BSD 3-clause licence](https://github.com/phpmd/phpmd/blob/master/LICENSE)

Note that these libraries are not distributed with res/liblod.
