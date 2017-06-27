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

use res\liblod\Rdf;

use PHPUnit\Framework\TestCase;

final class RdfTest extends TestCase
{
    private $rdf;

    public function setUp()
    {
         $this->rdf = new Rdf();
    }

    public function testGetLiteralLanguage()
    {
        $str = '"Judi Dench"@en-gb';
        $expected = 'en-gb';
        $actual = $this->rdf->getLiteralLanguageAndDatatype($str)['lang'];
        $this->assertEquals($expected, $actual);
    }

    public function testGetLiteralDatatype()
    {
        $str = '"Judi Dench"^^<http://foo.bar/mytype>';
        $expected = 'http://foo.bar/mytype';
        $actual = $this->rdf->getLiteralLanguageAndDatatype($str)['datatype'];
        $this->assertEquals($expected, $actual);
    }

    public function testGetLiteralValue()
    {
        $expected = 'Judi Dench';

        $str = '"Judi Dench"^^<http://foo.bar/mytype>';
        $actual = $this->rdf->getLiteralValue($str);
        $this->assertEquals($expected, $actual);

        $str = '"Judi Dench"@en-gb';
        $actual = $this->rdf->getLiteralValue($str);
        $this->assertEquals($expected, $actual);
    }

    public function testExpandPrefix()
    {
        $expected = 'http://purl.org/dc/dcmitype/StillImage';
        $actual = $this->rdf->expandPrefix('dcmitype:StillImage');
        $this->assertEquals($expected, $actual);
    }
}
