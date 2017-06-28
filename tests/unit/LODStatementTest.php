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

/* Unit tests for LODStatement */
use res\liblod\LODStatement;

use PHPUnit\Framework\TestCase;

final class LODStatementTest extends TestCase
{
    function testLiteralWithDatatype()
    {
        $lodstatement = new LODStatement(
            'http://foo.bar/something',
            'rdfs:label',
            array(
                'type' => 'literal',
                'value' => 'Fred Someone',
                'datatype' => 'xsd:string'
            )
        );

        $expected = '<http://foo.bar/something> <http://www.w3.org/2000/01/rdf-schema#label> "Fred Someone"^^<http://www.w3.org/2001/XMLSchema#string>';

        $actual = "$lodstatement";

        $this->assertEquals($expected, $actual);
    }

    function testLiteralWithLanguage()
    {
        $lodstatement = new LODStatement(
            'http://foo.bar/something',
            'rdfs:label',
            array(
                'type' => 'literal',
                'value' => 'Fred Frith',
                'lang' => 'en-gb'
            )
        );

        $expected = '<http://foo.bar/something> <http://www.w3.org/2000/01/rdf-schema#label> "Fred Frith"@en-gb';

        $actual = "$lodstatement";

        $this->assertEquals($expected, $actual);
    }

    function testObjectUri()
    {
        $lodstatement = new LODStatement(
            'http://foo.bar/something',
            'rdfs:label',
            array(
                'type' => 'uri',
                'value' => 'http://basel.bisel/roompa'
            )
        );

        $expected = '<http://foo.bar/something> <http://www.w3.org/2000/01/rdf-schema#label> <http://basel.bisel/roompa>';

        $actual = "$lodstatement";

        $this->assertEquals($expected, $actual);
    }
}
