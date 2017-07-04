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
use \GuzzleHttp\RequestOptions;
use \GuzzleHttp\Exception\ClientException;
use \DOMDocument;

/**
 * Wrapper round the Guzzle HTTP client, specialised for fetching and parsing
 * RDF.
 */
class HttpClient
{
    const RDF_TYPES = array('text/turtle', 'application/rdf+xml');

    /**
     * The HTTP user agent header value used in requests.
     * @property string $userAgent
     */
    public $userAgent = 'liblod/PHP';

    /**
     * The HTTP "Accept" header value used in requests.
     * @property string $accept
     */
    public $accept = 'text/turtle;q=0.95, application/rdf+xml;q=0.5, text/html;q=0.1';

    // Guzzle client
    private $client;

    /**
     * Constructor.
     *
     * @param GuzzleHttp\Client $client
     */
    public function __construct($client = NULL)
    {
        if(empty($client))
        {
            $client = new GuzzleClient(array(
                RequestOptions::ALLOW_REDIRECTS => array(
                    'max' => 10,
                    'track_redirects' => true
                )
            ));
        }

        $this->client = $client;
    }

    // returns TRUE if $typeValue matches one of the values in self::RDF_TYPES
    private function _isRdfType($typeValue = '')
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

    // make a LODResponse to encapsulate an error
    private function _makeErrorResponse($uri, $errorCode, $errMsg, $status = 0)
    {
        $lodresponse = new LODResponse();
        $lodresponse->status = $status;
        $lodresponse->target = $uri;
        $lodresponse->error = $errorCode;
        $lodresponse->errMsg = $errMsg;
        return $lodresponse;
    }

    /**
     * Extract <link rel="alternate" type="text/turtle|application/rdf+xml" ...>
     * from HTML.
     *
     * @param string $html HTML to parse for an RDF link
     * @param string $uri URI used to make the <link rel="alternate"> href absolute.
     *
     * for UriResolver::resolve()...
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function _getRdfLink($html, $uri)
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

                if($nodeRel === 'alternate' && $this->_isRdfType($nodeType))
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

        return ($location === NULL ? NULL : '' . $location);
    }

    // convert Guzzle response $rawResponse to LODResponse
    private function _convertResponse($uri, $rawResponse)
    {
        $status = $rawResponse->getStatusCode();

        // intelligible response, convert it
        $response = new LODResponse();
        $response->target = $uri;
        $response->payload = $rawResponse->getBody()->getContents();
        $response->status = $status;
        $response->type = $rawResponse->getHeader('Content-Type')[0];

        $contentLocation = $rawResponse->getHeader('Content-Location');
        if($contentLocation)
        {
            $response->contentLocation = $contentLocation[0];
        }
        else
        {
            $guzzleHeader = 'X-Guzzle-Redirect-History';
            $redirectUriHistory = $rawResponse->getHeader($guzzleHeader);
            $response->contentLocation = end($redirectUriHistory);
        }

        return $response;
    }

    // convert array of URIs to the format required by getAll()
    // 'originalUri' is used when fetching the alternate RDF representation of
    // an HTML page
    private function _normaliseRequestSpecsOrUris($requestSpecsOrUris)
    {
        // normalise the request specs or URIs so they can be processed
        /**
         * @SuppressWarnings docBlocks
         */
        $normaliseFn = function ($requestSpecOrUri)
        {
            if(is_string($requestSpecOrUri))
            {
                $requestSpecOrUri = array(
                    'uri' => $requestSpecOrUri,
                    'originalUri' => $requestSpecOrUri
                );
            }
            else if(empty($requestSpecOrUri['originalUri']))
            {
                $requestSpecOrUri['originalUri'] = $requestSpecOrUri['uri'];
            }

            return $requestSpecOrUri;
        };

        /**
         * for some reason, phpcheckstyle issues a warning about
         * $requestSpecsOrUris being unused, when it definitely is, so ignore
         * that
         * @SuppressWarnings checkUnusedVariables
         */
        return array_map($normaliseFn, $requestSpecsOrUris);
    }

    /**
     * Perform an HTTP GET and return a LODResponse.
     *
     * @param string $uri URI to fetch
     *
     * @return res\liblod\LODResponse
     */
    public function get($uri)
    {
        $lodresponses = $this->getAll(array(
            array('uri' => $uri, 'originalUri' => $uri)
        ));

        return $lodresponses[0];
    }

    /**
     * Get multiple URIs via HTTP GET.
     *
     * @param array $requestSpecsOrUris Array of arrays in format
     * {'uri' => uri to fetch, 'originalUri' => original URI which led to
     * fetching this one}
     * OR array of URIs (which get converted to this format)
     *
     * @return array Array of LODResponse objects
     */
    public function getAll($requestSpecsOrUris)
    {
        $requestSpecs = $this->_normaliseRequestSpecsOrUris($requestSpecsOrUris);

        $headers = array(
            'Accept' => $this->accept,
            'User-Agent' => $this->userAgent
        );

        $requests = array();
        foreach($requestSpecs as $requestSpec)
        {
            $requests[] = new Request('GET', $requestSpec['uri'], $headers);
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

            // if the content type isn't available, we won't be able to do
            // anything with the response, so add an error and return
            if(count($contentType) < 1)
            {
                $msg = 'no content type';
                $lodresponses[$index] = $this->_makeErrorResponse($uri, 1, $msg);

                return;
            }

            $htmlContentType = preg_match('|text/html|', $contentType[0]);
            $isHtml = ($htmlContentType && $response->getStatusCode() === 200);

            if($isHtml)
            {
                // get <link> out of HTML page and fetch that instead
                $html = $response->getBody()->getContents();
                $location = $this->_getRdfLink($html, $uri);

                if($location)
                {
                    $needsFetch[] = array(
                        'uri' => $location,
                        'originalUri' => $uri
                    );

                    return;
                }

                // don't know what to do, so add an error
                $errMsg = 'HTML page but no RDF link';
                $errResponse = $this->_makeErrorResponse($uri, 1, $errMsg);
                $lodresponses[$index] = $errResponse;

                return;
            }

            // not HTML, so convert it to a LODResponse
            $lodresponse = $this->_convertResponse($originalUri, $response);
            $lodresponses[$index] = $lodresponse;
        };

        // status code 500 gets captured here
        $rejectedFn = function($reason, $index) use(&$lodresponses, $requestSpecs)
        {
            $uri = $requestSpecs[$index]['uri'];
            $lodresponses[$index] = $this->_makeErrorResponse($uri, 1, $reason);
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
            $rdfVariants = $this->getAll($needsFetch);
            $lodresponses = array_merge($lodresponses, $rdfVariants);
        }

        return $lodresponses;
    }
}
