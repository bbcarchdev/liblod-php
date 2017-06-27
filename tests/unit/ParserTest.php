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

/* Unit tests for LOD */
use res\liblod\Parser;

use PHPUnit\Framework\TestCase;

const PARSER_TURTLE = <<<TURTLE
@prefix dcterms: <http://purl.org/dc/terms/> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix schema: <http://schema.org/> .
@prefix foo: <http://foo.bar/> .

<http://foo.bar/something>
  dcterms:title "Bar" ;
  rdfs:label "Foo" ;
  schema:name "Baz" ;
  foo:appelation "Boo" .
TURTLE;

const PARSER_RDFXML = <<<RDFXML
<?xml version="1.0" encoding="utf-8" ?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:dc="http://purl.org/dc/terms/"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:schema="http://schema.org/"
         xmlns:foo="http://foo.bar/">
  <rdf:Description rdf:about="http://foo.bar/something">
    <dc:title>Bar</dc:title>
    <rdfs:label>Foo</rdfs:label>
    <schema:name>Baz</schema:name>
    <foo:appelation>Boo</foo:appelation>
  </rdf:Description>
</rdf:RDF>
RDFXML;

final class ParserTest extends TestCase
{
    function testParse()
    {
        $parser = new Parser();

        $triplesTurtle = $parser->parse(PARSER_TURTLE, 'text/turtle');
        $triplesRdfXml = $parser->parse(PARSER_RDFXML, 'application/rdf+xml');

        $this->assertEquals(4, count($triplesTurtle));
        $this->assertEquals(4, count($triplesRdfXml));
        $this->assertEquals($triplesTurtle, $triplesRdfXml);
    }

    function testParseBadContentType()
    {
        $this->expectException(Error::class);

        $parser = new Parser();
        $parser->parse(PARSER_TURTLE, 'text/html');
    }
}