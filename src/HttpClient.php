<?php
namespace res\liblod;

use res\liblod\LODResponse;

use \GuzzleHttp\Psr7\UriResolver;
use \GuzzleHttp\Psr7\Uri;
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
    // $uri = URI to fetch
    // these two arguments are used to manage recursive calls:
    // $originalUri = original URI we were trying to get (to
    // track through redirects)
    public function get($uri, $originalUri=NULL)
    {
        if(!$originalUri)
        {
            $originalUri = $uri;
        }

        $options = array(
            'allow_redirects' => array(
                'max' => $this->maxRedirects
            ),
            'headers' => array(
                'Accept' => $this->accept,
                'User-Agent' => $this->userAgent
            )
        );

        try
        {
            $rawResponse = $this->client->get($uri, $options);
        }
        catch(\GuzzleHttp\Exception\ClientException $e)
        {
            $response = new LODResponse();
            $response->target = $uri;
            $response->error = 1;
            $response->errMsg = $e->getMessage();
            return $response;
        }

        $contentType = $rawResponse->getHeader('Content-Type');
        if($contentType)
        {
            $contentType = $contentType[0];
        }

        $isHtml = ($rawResponse->getStatusCode() === 200 &&
                   preg_match('|text/html|', $contentType));

        if($isHtml)
        {
            // get <link> out of HTML page and fetch that instead
            $html = $rawResponse->getBody()->getContents();
            $location = $this->getRdfLink($html, $uri);

            if($location)
            {
                return $this->get($location, $originalUri);
            }
        }
        else
        {
            return $this->convertResponse($originalUri, $rawResponse);
        }
    }
}
