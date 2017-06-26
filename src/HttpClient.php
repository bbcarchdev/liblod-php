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

namespace res\liblod;

use res\liblod\LODResponse;

use \GuzzleHttp\Psr7\UriResolver;
use \GuzzleHttp\Psr7\Uri;
use \GuzzleHttp\Psr7\Request;
use \GuzzleHttp\Pool;
use \GuzzleHttp\Client as GuzzleClient;
use \GuzzleHttp\Exception\ClientException;
use \DOMDocument;

class HttpClient
{
    const RDF_TYPES = array('text/turtle', 'application/rdf+xml');

    /* Guzzle client */
    private $client;

    /* The HTTP user agent used in requests */
    public $userAgent = 'liblod/PHP';

    /* The HTTP "Accept" header used in requests */
    public $accept = 'text/turtle;q=0.95, application/rdf+xml;q=0.5, text/html;q=0.1';

    /* Maximum number of redirects to follow */
    public $maxRedirects = 10;

    public function __construct()
    {
        $this->client = new GuzzleClient();
    }

    // returns TRUE if $typeValue matches one of the values in self::RDF_TYPES
    private function isRdfType($typeValue='')
    {
        foreach(self::RDF_TYPES as $rdfType)
        {
            if(preg_match('|^' . $rdfType . '|', $typeValue))
            {
                return TRUE;
            }
        }
        return FALSE;
    }

    // $uri is used to make the <link rel="alternate"> href absolute
    private function getRdfLink($html, $uri)
    {
        $location = NULL;

        $doc = new DOMDocument();

        // suppress errors to allow PHP to correctly parse HTML 5
        // (see http://stackoverflow.com/questions/9149180/domdocumentloadhtml-error)
        $loaded = @$doc->loadHTML($html);

        if ($loaded)
        {
            $nodes = $doc->getElementsByTagName('link');
            foreach($nodes as $node)
            {
                $nodeType = $node->getAttribute('type');
                $nodeRel = $node->getAttribute('rel');

                if($nodeRel === 'alternate' && $this->isRdfType($nodeType))
                {
                    $location = $node->getAttribute('href');
                    break;
                }
            }
        }

        if($location && (substr($location, 0, 4) !== 'http'))
        {
            // resolve location relative to URI
            $base = new Uri($uri);
            $rel = new Uri($location);
            $location = UriResolver::resolve($base, $rel);
        }

        return "$location";
    }

    // convert Guzzle response $rawResponse to LODResponse
    private function convertResponse($uri, $rawResponse)
    {
        // this should be set in conditional below
        $response = NULL;

        $status = $rawResponse->getStatusCode();

        // bad response
        if($status >= 500)
        {
            $response = new LODResponse();
            $response->target = $uri;
            $response->error = 1;
            $response->status = $status;
            $response->errMsg = $rawResponse->getReasonPhrase();
        }
        // intelligible response, convert it
        else
        {
            $response = new LODResponse();
            $response->target = $uri;
            $response->payload = $rawResponse->getBody()->getContents();
            $response->status = $status;
            $response->type = $rawResponse->getHeader('Content-Type')[0];

            $contentLocation = $rawResponse->getHeader('Content-Location');
            if($contentLocation)
            {
                $contentLocation = $contentLocation[0];
            }
            else
            {
                $contentLocation = $uri;
            }
            $response->contentLocation = $contentLocation;
        }

        return $response;
    }

    // perform an HTTP GET and return a LODResponse
    // $uri: URI to fetch
    public function get($uri)
    {
        $lodresponses = $this->getAll(array(
            array('uri' => $uri, 'originalUri' => $uri)
        ));

        return $lodresponses[0];
    }

    // $requestSpecs: array of arrays in format
    // {'uri' => uri to fetch, 'originalUri' => original URI which led to
    // fetching this one}
    // or array of URIs (which get converted to this format)
    // 'originalUri' is used when fetching the alternate RDF representation of
    // an HTML page
    function getAll($requestSpecsOrUris)
    {
        $requestSpecs = array();
        foreach($requestSpecsOrUris as $requestSpecOrUri)
        {
            if(is_string($requestSpecOrUri))
            {
                $requestSpecs[] = array(
                    'uri' => $requestSpecOrUri,
                    'originalUri' => $requestSpecOrUri
                );
            }
            else
            {
                if(empty($requestSpecOrUri['originalUri']))
                {
                    $requestSpecOrUri['originalUri'] = $requestSpecOrUri['uri'];
                }
                $requestSpecs[] = $requestSpecOrUri;
            }
        }

        $requestOptions = array(
            'allow_redirects' => array(
                'max' => $this->maxRedirects
            ),
            'headers' => array(
                'Accept' => $this->accept,
                'User-Agent' => $this->userAgent
            )
        );

        $requests = array();
        foreach($requestSpecs as $requestSpec)
        {
            $uri = $requestSpec['uri'];
            $requests[] = new Request('GET', $uri, $requestOptions['headers']);
        }

        // array of LODResponse objects
        $lodresponses = array();

        // array of URIs which need to be re-fetched because they are
        // RDF variants extracted from <link> elements in HTML pages
        $needsFetch = array();

        // build the pool to send the requests in parallel; need some closures
        // to deal with responses as they arrive
        $fulfilledFn = function($response, $index) use($requestSpecs, &$lodresponses, &$needsFetch)
        {
            $uri = $requestSpecs[$index]['uri'];
            $originalUri = $requestSpecs[$index]['originalUri'];

            $contentType = $response->getHeader('Content-Type');
            if($contentType)
            {
                $contentType = $contentType[0];
            }

            $isHtml = ($response->getStatusCode() === 200 &&
                       preg_match('|text/html|', $contentType));

            if($isHtml)
            {
                // get <link> out of HTML page and fetch that instead
                $html = $response->getBody()->getContents();
                $location = $this->getRdfLink($html, $uri);

                if($location)
                {
                    $needsFetch[] = array(
                        'uri' => $location,
                        'originalUri' => $uri
                    );
                }
                else
                {
                    $lodresponse = new LODResponse();
                    $lodresponse->target = $uri;
                    $lodresponse->error = 1;
                    $lodresponse->errMsg = 'HTML page but not RDF link';
                    $lodresponses[$index] = $lodresponse;
                }
            }
            else
            {
                $lodresponse = $this->convertResponse($originalUri, $response);
                $lodresponses[$index] = $lodresponse;
            }
        };

        $rejectedFn = function($reason, $index) use(&$lodresponses, $requestSpecs)
        {
            $lodresponse = new LODResponse();
            $lodresponse->target = $requestSpecs[$index]['uri'];
            $lodresponse->error = 1;
            $lodresponse->errMsg = $reason;
            $lodresponses[$index] = $lodresponse;
        };

        $options = array(
            'concurrency' => 10,
            'fulfilled' => $fulfilledFn,
            'rejected' => $rejectedFn
        );

        $pool = new Pool($this->client, $requests, $options);

        // wait for all responses to be returned
        $pool->promise()->wait();

        // fetch the RDF variants of HTML pages
        if(count($needsFetch) > 0)
        {
            $lodresponses = array_merge(
                $lodresponses,
                $this->getAll($needsFetch)
            );
        }

        return $lodresponses;
    }
}
