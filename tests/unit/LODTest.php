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
use res\liblod\LOD;
use res\liblod\LODResponse;

use PHPUnit\Framework\TestCase;

const TURTLE = <<<TURTLE
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

final class FakeHttpClient
{
    private $responseMap;

    // $response: either a canned LODResponse object returned for all requests,
    // or an associative array mapping from URIs to LODResponse objects;
    // if a single response, this is mapped to the special URI pattern '*',
    // meaning this response is returned for all request URIs
    public function __construct($response)
    {
        if($response instanceof LODResponse)
        {
            $this->responseMap = array(
                '*' => $response
            );
        }
        else
        {
            $this->responseMap = $response;
        }
    }

    public function get($uri)
    {
        if(array_key_exists('*', $this->responseMap))
        {
            return $this->responseMap['*'];
        }
        else if(array_key_exists($uri, $this->responseMap))
        {
            return $this->responseMap[$uri];
        }
        else
        {
            trigger_error("no fake response available for $uri");
        }
    }
}

final class LODTest extends TestCase
{
    function testSetPrefix()
    {
        $lod = new LOD();
        $lod->setPrefix('bar', 'http://foo.bar/');

        $lod->loadRdf(TURTLE, 'text/turtle');
        $lodinstance = $lod['http://foo.bar/something'];

        $expected = 'Boo';
        $actual = "{$lodinstance['bar:appelation']}";
        $this->assertEquals($expected, $actual);
    }

    function testResolve()
    {
        $fakeResponse = new LODResponse();
        $fakeResponse->payload = TURTLE;
        $fakeResponse->type = 'text/turtle';

        $fakeClient = new FakeHttpClient($fakeResponse);

        $lod = new LOD($fakeClient);
        $lodinstance = $lod->resolve('http://foo.bar/something');

        $this->assertEquals(4, count($lodinstance->model));
    }

    function testLoadRdfTurtle()
    {
        $lod = new LOD();
        $lod->loadRdf(TURTLE, 'text/turtle');
        $lodinstance = $lod->resolve('http://foo.bar/something');
        $this->assertEquals(4, count($lodinstance->model));
    }
}