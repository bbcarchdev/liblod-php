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
case "$1" in
  install)
    echo "Installing dependencies"
    php tools/composer.phar install
  ;;
  unit)
    echo "Running unit test suite"
    php tools/phpunit.phar --bootstrap vendor/autoload.php tests/unit
  ;;
  int)
    echo "Running integration test suite"
    php tools/phpunit.phar --bootstrap vendor/autoload.php tests/integration
  ;;
  cov)
    echo "Running unit test suite with code coverage reporting; see cov/index.html"
    php tools/phpunit.phar --bootstrap vendor/autoload.php --whitelist src --coverage-html cov tests/unit
  ;;
  mess)
    echo "Running code quality analysis with PHPMD"
    result=`php vendor/bin/phpmd src text cleancode,codesize,design,naming,unusedcode`
    echo
    if [ "x" = "x$result" ] ; then
      echo "*** No code quality problems found"
    else
      echo "!!! Problems found:"
      echo $result
    fi
  ;;
  *)
    echo "./build.sh install|unit|int|cov|mess"
  ;;
esac
