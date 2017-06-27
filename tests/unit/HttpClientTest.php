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

/* Unit tests for HttpClient */
use res\liblod\HttpClient;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

use PHPUnit\Framework\TestCase;

const HTTPCLIENT_TURTLE = <<<TURTLE
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://foo.baz/something>
    rdfs:label "An exciting thing"@en .

<http://foo.baz/something.rdf>
    rdfs:label "Data about 'An exciting thing'"@en ;
    foaf:primaryTopic <http://foo.baz/something> .
TURTLE;

const HTTPCLIENT_HTML = <<<HTML
<!doctype html>
<html>
  <head>
    <link rel="alternate" type="text/turtle" href="/something.rdf">
  </head>
</html>
HTML;

const HTTPCLIENT_HTML_NO_LINK = <<<HTML
<!doctype html><html></html>
HTML;

const HTTPCLIENT_HTML_WRONG_LINK_TYPE = <<<HTML
<!doctype html>
<html>
  <link rel="alternate" type="application/json" href="/something.json">
</html>
HTML;

final class HttpClientTest extends TestCase
{
    // create an HttpClient instance with a mocked Guzzle client underlying it
    // $responses is an array of Guzzle Response objects;
    // see http://guzzle.readthedocs.io/en/stable/testing.html#mock-handler
    // for examples
    private function initClient($responses)
    {
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $guzzleClient = new GuzzleClient(['handler' => $handler]);
        return new HttpClient($guzzleClient);
    }

    function testFetchBadResponse()
    {
        $client = $this->initClient(array(
            new Response(500)
        ));

        $lodresponse = $client->get('http://foo.baz/something');

        $this->assertEquals(1, $lodresponse->error);
    }

    function testFetchRdf()
    {
        $headers = array('Content-Type' => 'text/turtle');

        $client = $this->initClient(array(
            new Response(200, $headers, HTTPCLIENT_TURTLE)
        ));

        $lodresponse = $client->get('http://foo.baz/something');

        $this->assertEquals(200, $lodresponse->status);
        $this->assertEquals(HTTPCLIENT_TURTLE, $lodresponse->payload);
        $this->assertEquals('text/turtle', $lodresponse->type);
    }

    // test fetching HTML with a link to an RDF variant
    function testFetchHtml()
    {
        $htmlHeaders = array('Content-Type' => 'text/html');
        $htmlResponse = new Response(200, $htmlHeaders, HTTPCLIENT_HTML);

        $rdfHeaders = array(
            'Content-Type' => 'text/turtle',
            'Content-Location' => '/something.rdf'
        );
        $rdfResponse = new Response(200, $rdfHeaders, HTTPCLIENT_TURTLE);

        $client = $this->initClient(array($htmlResponse, $rdfResponse));

        $lodresponse = $client->get('http://foo.baz/something');

        $this->assertEquals(200, $lodresponse->status);
        $this->assertEquals(HTTPCLIENT_TURTLE, $lodresponse->payload);
        $this->assertEquals('text/turtle', $lodresponse->type);
        $this->assertEquals('/something.rdf', $lodresponse->contentLocation);
    }

    // test fetching HTML with no link
    function testFetchHtmlNoLink()
    {
        $htmlHeaders = array('Content-Type' => 'text/html');

        $client = $this->initClient(array(
            new Response(200, $htmlHeaders, HTTPCLIENT_HTML_NO_LINK)
        ));

        $lodresponse = $client->get('http://foo.baz/something');
        $this->assertEquals(1, $lodresponse->error);
    }

    // test fetching HTML with bad type on link
    function testFetchHtmlWrongLinkType()
    {
        $htmlHeaders = array('Content-Type' => 'text/html');

        $client = $this->initClient(array(
            new Response(200, $htmlHeaders, HTTPCLIENT_HTML_WRONG_LINK_TYPE)
        ));

        $lodresponse = $client->get('http://foo.baz/something');
        $this->assertEquals(1, $lodresponse->error);
    }

    function testFetchRdfBadContentType()
    {
        $client = $this->initClient(array(
            new Response(200, array(), HTTPCLIENT_TURTLE)
        ));

        $lodresponse = $client->get('http://foo.baz/something');

        $this->assertEquals(1, $lodresponse->error);
    }

    // this will do 3 GETs: one for HTML page with RDF link, one for RDF
    // page without an RDF link, and one for the RDF page linked from the
    // first HTML page
    function testGetAll()
    {
        $htmlHeaders = array('Content-Type' => 'text/html');
        $htmlResponse1 = new Response(200, $htmlHeaders, HTTPCLIENT_HTML);
        $htmlResponse2 = new Response(200, $htmlHeaders, HTTPCLIENT_HTML_NO_LINK);

        $rdfHeaders = array(
            'Content-Type' => 'text/turtle',
            'Content-Location' => '/something.rdf'
        );
        $rdfResponse = new Response(200, $rdfHeaders, HTTPCLIENT_TURTLE);

        $client = $this->initClient(array(
            $htmlResponse1,
            $htmlResponse2,
            $rdfResponse
        ));

        $lodresponses = $client->getAll(array(
            'http://foo.baz/something.html',
            array('uri' => 'http://foo.baz/something')
        ));

        $this->assertEquals(2, count($lodresponses));
        foreach($lodresponses as $lodresponse)
        {
            if($lodresponse->type === 'text/turtle')
            {
                $this->assertEquals(HTTPCLIENT_TURTLE, $lodresponse->payload);
            }
        }
    }
}