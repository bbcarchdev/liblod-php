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
use res\liblod\Rdf;

use PHPUnit\Framework\TestCase;

const LOD_TURTLE = <<<TURTLE
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

const LOD_TURTLE_SAMEAS = <<<TURTLE_SAMEAS
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

        $lod->loadRdf(LOD_TURTLE, 'text/turtle');
        $lodinstance = $lod['http://foo.bar/something'];

        $expected = 'Boo';
        $actual = "{$lodinstance['bar:appelation']}";
        $this->assertEquals($expected, $actual);
    }

    function testFetchAll()
    {
        $fakeResponse1 = new LODResponse();
        $fakeResponse1->payload = LOD_TURTLE;
        $fakeResponse1->type = 'text/turtle';

        $fakeResponse2 = new LODResponse();
        $fakeResponse2->payload = LOD_TURTLE_SAMEAS;
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

    function testFetchBadResponse()
    {
        $uri = 'http://foo.bar/something';

        $fakeResponse = new LODResponse();
        $fakeResponse->target = $uri;
        $fakeResponse->error = 1;

        $fakeClient = new FakeHttpClient($fakeResponse);

        $lod = new LOD($fakeClient);

        $this->assertEquals(FALSE, $lod->fetch($uri),
                            'fetch with error response should return FALSE');
    }

    function testResolve()
    {
        $fakeResponse = new LODResponse();
        $fakeResponse->payload = LOD_TURTLE;
        $fakeResponse->type = 'text/turtle';

        $fakeClient = new FakeHttpClient($fakeResponse);

        $lod = new LOD($fakeClient);
        $lodinstance = $lod->resolve('http://foo.bar/something');

        $this->assertEquals(4, count($lodinstance->model));
    }

    function testSameAs()
    {
        $lod = new LOD();
        $lod->loadRdf(LOD_TURTLE_SAMEAS, 'text/turtle');

        $expected = array(
            'http://foo.bar/somethingelse',
            'http://foo.bar/somethingelseagain'
        );

        $actual = $lod->getSameAs('http://foo.bar/something');

        $this->assertEquals($expected, $actual);
    }

    function testOffsetSetThrowsException()
    {
        $lod = new LOD();
        $this->expectException(PHPUnit_Framework_Error_Notice::class);
        $lod->offsetSet(0, 'bar');
    }

    function testOffsetUnsetThrowsException()
    {
        $lod = new LOD();
        $this->expectException(PHPUnit_Framework_Error_Notice::class);
        $lod->offsetUnset(0);
    }

    function testOffsetExists()
    {
        $lod = new LOD();
        $lod->loadRdf(LOD_TURTLE, 'text/turtle');

        $expected = TRUE;
        $actual = $lod->offsetExists('http://foo.bar/something');
        $this->assertEquals($expected, $actual);

        $expected = FALSE;
        $actual = $lod->offsetExists(99);
        $this->assertEquals($expected, $actual);
    }

    function testIsset()
    {
        $lod = new LOD();
        $lod->loadRdf(LOD_TURTLE, 'text/turtle');
        $this->assertEquals(TRUE, isset($lod['http://foo.bar/something']));
    }

    function testProperties()
    {
        $lod = new LOD();
        $this->assertEquals(NULL, $lod->foo);

        $this->assertEquals(array(), $lod->index);

        $props = array('subject', 'document', 'errMsg');
        foreach($props as $prop)
        {
            $this->assertEquals(NULL, $lod->{$prop});
        }

        $props = array('error', 'status');
        foreach($props as $prop)
        {
            $this->assertEquals(0, $lod->{$prop});
        }

        // test non-existent property
        $this->assertEquals(FALSE, isset($lod->foo));

        // arbitrary properties can be set and unset
        $lod->foo = 'bar';
        $this->assertEquals('bar', $lod->foo);
        unset($lod->foo);
        $this->assertEquals(FALSE, isset($lod->foo));
    }

    function testSubject()
    {
        $lod = new LOD();
        $this->assertEquals(FALSE, isset($lod->subject));

        $this->expectException(PHPUnit_Framework_Error_Warning::class);
        $lod->subject = 'foo';
    }

    function testDocument()
    {
        $lod = new LOD();
        $this->assertEquals(FALSE, isset($lod->document));

        $this->expectException(PHPUnit_Framework_Error_Warning::class);
        $lod->document = 'foo';
    }

    function testStatus()
    {
        $lod = new LOD();
        $this->assertEquals(FALSE, isset($lod->status));

        $this->expectException(PHPUnit_Framework_Error_Warning::class);
        $lod->status = 200;
    }

    function testError()
    {
        $lod = new LOD();
        $this->assertEquals(FALSE, isset($lod->error));

        $this->expectException(PHPUnit_Framework_Error_Warning::class);
        $lod->error = 1;
    }

    function testErrMsg()
    {
        $lod = new LOD();
        $this->assertEquals(FALSE, isset($lod->errMsg));

        $this->expectException(PHPUnit_Framework_Error_Warning::class);
        $lod->errMsg = 1;
    }

    function testIndex()
    {
        $lod = new LOD();
        $this->assertEquals(TRUE, isset($lod->index));

        $this->expectException(PHPUnit_Framework_Error_Warning::class);
        $lod->index = array();
    }

    function testLoadRdfTurtle()
    {
        $lod = new LOD();
        $lod->loadRdf(LOD_TURTLE, 'text/turtle');
        $lodinstance = $lod->resolve('http://foo.bar/something');
        $this->assertEquals(4, count($lodinstance->model));
    }
}