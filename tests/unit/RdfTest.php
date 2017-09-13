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

use bbcarchdev\liblod\Rdf;
use bbcarchdev\liblod\LOD;

use PHPUnit\Framework\TestCase;

const RDF_TURTLE = <<<TURTLE
@prefix dc: <http://purl.org/dc/terms/> .
@prefix foaf: <http://xmlns.com/foaf/0.1/> .
@prefix owl: <http://www.w3.org/2002/07/owl#>.
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix void: <http://rdfs.org/ns/void#> .

<http://res/people/william-blake#id>
  a foaf:Person ;
  rdfs:label "William Blake"@en-GB ;
  dc:comment "William Blake, the person"@en-GB ;
  owl:sameAs <http://dbpedia.org/resource/William_Blake> ;
  void:inDataset <http://res/people#dataset> ;
  foaf:primaryTopicOf <http://res/people/william-blake> .
TURTLE;

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

    public function testToTurtle()
    {
        $lod = new LOD();
        $lod->loadRdf(RDF_TURTLE, 'text/turtle');

        $lodturtle = $this->rdf->toTurtle($lod);

        $lodinstance = $lod['http://res/people/william-blake#id'];
        $lodinstanceturtle = $this->rdf->toTurtle($lodinstance);

        $this->assertEquals($lodturtle, $lodinstanceturtle);
    }
}
