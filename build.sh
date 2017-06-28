#!/bin/bash

# Copyright 2017 BBC
# Author: Elliot Smith <elliot.smith@bbc.co.uk>
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#   http://www.apache.org/licenses/LICENSE-2.0
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
#
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

# script to make it a bit easier to run tests etc.
function unit() {
  echo "Running unit test suite"
  php tools/phpunit.phar --bootstrap vendor/autoload.php tests/unit
}

function int() {
  echo "Running integration test suite"
  php tools/phpunit.phar --bootstrap vendor/autoload.php tests/integration
}

function cov() {
  echo "Running unit test suite with code coverage reporting"
  php tools/phpunit.phar --bootstrap vendor/autoload.php --whitelist src --coverage-html cov tests/unit
  echo "Coverage report generated; see cov/index.html"
}

function mess() {
  echo "Running code quality analysis with PHPMD"
  result=`php vendor/bin/phpmd src text cleancode,codesize,design,naming,unusedcode`
  echo
  if [ "x" = "x$result" ] ; then
    echo "*** No code quality problems found"
  else
    echo "!!! Problems found:"
    echo $result
  fi
}

function style() {
  echo "Checking code style using phpcheckstyle"
  rm -Rf style-report
  php vendor/phpcheckstyle/phpcheckstyle/run.php --src src/ --config phpcheckstyle-config.xml
  echo "Code style report generated; see style-report/index.html"
}

function docs() {
  echo "Generating API documentation in apidocs/ using phpDocumentor"
  rm -Rf apidocs/
  php tools/phpDocumentor.phar -d src -t apidocs --template=responsive-twig
  echo "API documentation generated in apidocs/ directory"
}

case "$1" in
  install)
    echo "Installing dependencies"
    php tools/composer.phar install
  ;;
  unit)
    unit
  ;;
  int)
    int
  ;;
  cov)
    cov
  ;;
  mess)
    mess
  ;;
  style)
    style
  ;;
  docs)
    docs
  ;;
  *)
    echo "./build.sh install|unit|int|cov|mess|style"
  ;;
esac
