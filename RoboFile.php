<?php
/*
 * Copyright 2017 BBC
 *
 * Author: Elliot Smith <elliot.smith@bbc.co.uk>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use \Robo\Tasks as RoboTasks;

class RoboFile extends RoboTasks
{
    /**
     * Run unit tests
     */
    public function unit()
    {
        $this->taskExec('php ' . __DIR__ . '/tools/phpunit.phar')
             ->option('bootstrap', './vendor/autoload.php')
             ->arg('tests/unit')
             ->run();
    }

    /**
     * Run integration tests
     */
    public function int()
    {
        $this->taskExec('php ' . __DIR__ . '/tools/phpunit.phar')
             ->option('bootstrap', './vendor/autoload.php')
             ->arg('tests/integration')
             ->run();
    }

    /**
     * Run unit tests with code coverage report
     * @param string $dir Output directory for coverage report
     */
    function cov($dir = 'build/cov')
    {
        $this->taskDeleteDir($dir)->run();

        $this->taskExec('php')
             ->arg(__DIR__ . '/tools/phpunit.phar')
             ->option('bootstrap', './vendor/autoload.php')
             ->option('whitelist', 'src')
             ->option('coverage-html', $dir)
             ->arg('tests/unit')
             ->run();

        $this->say("Coverage report is in $dir/index.html");
    }

    /**
     * Run PHPMD to find issues with the code quality
     */
    function mess()
    {
        $this->say('Checking for code quality issues using PHPMD');

        $this->taskExec('php')
             ->arg('vendor/bin/phpmd')
             ->arg('src')
             ->arg('text')
             ->arg('cleancode,codesize,design,naming,unusedcode')
             ->run();
    }

    /**
     * Run phpcheckstyle to check code style
     * @param string $dir Output directory for report
     */
     function style($dir = 'build/style')
    {
        $this->say('Checking code style using phpcheckstyle');

        $this->taskDeleteDir($dir)->run();

        $this->taskExec('php')
             ->arg('vendor/phpcheckstyle/phpcheckstyle/run.php')
             ->option('outdir', $dir)
             ->option('src', 'src/')
             ->option('config', 'phpcheckstyle-config.xml')
             ->run();

        $this->say("Code style report generated; see $dir/index.html");
    }

    /**
     * Generate API docs using phpDocumentor
     * @param string $dir Output directory for API docs HTML files
     */
    function docs($dir = 'build/apidocs')
    {
        $this->say("Generating API documentation in $dir using phpDocumentor");

        $this->taskDeleteDir($dir)->run();

        $this->taskExec('php')
             ->arg('tools/phpDocumentor.phar')
             ->option('directory', 'src')
             ->option('target', $dir)
             ->option('template', 'responsive-twig')
             ->run();

        $this->say("API documentation generated in $dir directory");
    }
}