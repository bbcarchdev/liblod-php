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

const TURTLE_SAMEAS = <<<TURTLE_SAMEAS
@prefix owl: <http://www.w3.org/2002/07/owl#> .

<http://foo.bar/somethingelse>
  owl:sameAs <http://foo.bar/something> .

<http://foo.bar/somethingelseagain>
  owl:sameAs <http://foo.bar/something> .
TURTLE_SAMEAS;

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

    public function getAll($uris)
    {
        $responses = array();

        foreach($uris as $uri)
        {
            $responses[] = $this->get($uri);
        }

        return $responses;
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

    function testFetchAll()
    {
        $fakeResponse1 = new LODResponse();
        $fakeResponse1->payload = TURTLE;
        $fakeResponse1->type = 'text/turtle';

        $fakeResponse2 = new LODResponse();
        $fakeResponse2->payload = TURTLE_SAMEAS;
        $fakeResponse2->type = 'text/turtle';

        $fakeClient = new FakeHttpClient(
            array(
                'http://foo.bar/something' => $fakeResponse1,
                'http://foo.bar/somethingelse' => $fakeResponse2
            )
        );

        $lod = new LOD($fakeClient);
        $lod->fetchAll(
            array('http://foo.bar/something', 'http://foo.bar/somethingelse')
        );

        // check each response has been processed
        $lodinstance1 = $lod->locate('http://foo.bar/something');
        $this->assertEquals(4, count($lodinstance1->model));

        $lodinstance2 = $lod->locate('http://foo.bar/somethingelse');
        $this->assertEquals(1, count($lodinstance2->model));
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

    function testSameAs()
    {
        $lod = new LOD();
        $lod->loadRdf(TURTLE_SAMEAS, 'text/turtle');

        $expected = array(
            'http://foo.bar/somethingelse',
            'http://foo.bar/somethingelseagain'
        );

        $actual = $lod->getSameAs('http://foo.bar/something');

        $this->assertEquals($expected, $actual);
    }

    function testLoadRdfTurtle()
    {
        $lod = new LOD();
        $lod->loadRdf(TURTLE, 'text/turtle');
        $lodinstance = $lod->resolve('http://foo.bar/something');
        $this->assertEquals(4, count($lodinstance->model));
    }
}