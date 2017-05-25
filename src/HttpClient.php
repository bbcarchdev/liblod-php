<?php
namespace res\liblod;

require_once(dirname(__FILE__) . '/../vendor/autoload.php');

use res\liblod\LODResponse;

use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Uri;
use \DOMDocument;

class HttpClient
{
    const RDF_TYPES = array('text/turtle', 'application/rdf+xml');

    /* The cURL handle used for fetches */
    public $curl;

    /* The HTTP user agent used in requests */
    public $userAgent = 'liblod/PHP';

    /* The HTTP "Accept" header used in requests */
    public $accept = 'text/turtle;q=0.95, application/rdf+xml;q=0.5, text/html;q=0.1';

    /* Maximum number of redirects to follow */
    public $maxRedirects = 10;

    public function __construct()
    {
        $curl = curl_init();
        //curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
        //curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_USERAGENT, $this->userAgent);

        $headers = array('Accept: ' . $this->accept);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $this->curl = $curl;
    }

    public function __destruct()
    {
        if($this->curl) {
            curl_close($this->curl);
            $this->curl = NULL;
        }
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

        return $location;
    }

    // perform an HTTP GET and return a LODResponse
    // $uri = URI to fetch
    // these two arguments are used to manage recursive calls:
    // $numRedirects = number of redirects we've already tried to follow
    // $originalUri = original URI we were trying to get (to
    // track through redirects)
    public function get($uri, $numRedirects=0, $originalUri=NULL)
    {
        if(!$originalUri)
        {
            $originalUri = $uri;
        }

        // already too many redirects, early return
        if($numRedirects > $this->maxRedirects)
        {
            $response = new LODResponse();
            $response->target = $originalUri;
            $response->error = CURLE_TOO_MANY_REDIRECTS;
            $response->errMsg = 'Too many redirects; max. is ' .
                                $this->maxRedirects;
            return $response;
        }

        // below redirect limit, so do the fetch
        curl_setopt($this->curl, CURLOPT_URL, $uri);

        // set up handler for headers to get the content location
        $contentLocation = NULL;

        curl_setopt(
            $this->curl,
            CURLOPT_HEADERFUNCTION,
            function($curl, $headerLine) use(&$contentLocation)
            {
                $header = explode(':', $headerLine);
                $headerName = strtolower(trim($header[0]));
                if ($headerName === 'content-location')
                {
                    $contentLocation = trim($header[1]);
                }

                return strlen($headerLine);
            }
        );

        // send request
        $raw_response = curl_exec($this->curl);

        // this should be set below
        $response = NULL;

        // no response
        if($raw_response === FALSE)
        {
            $response = new LODResponse();
            $response->target = $originalUri;
            $response->error = curl_errno($this->curl);
            $response->errMsg = curl_error($this->curl);
        }
        // intelligible response, parse it
        else
        {
            $status = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
            $type = curl_getinfo($this->curl, CURLINFO_CONTENT_TYPE);

            // 300 code or HTML page with <link> in it - follow redirect
            $isRedirect = ($status >= 300 && $status <= 399);
            $isHtml = ($status === 200 && preg_match('|text/html|', $type));

            if($isRedirect || $isHtml)
            {
                $location = NULL;

                if($isHtml)
                {
                    // get <link> out of HTML page and fetch that instead
                    $location = $this->getRdfLink($raw_response, $uri);
                }
                else
                {
                    // get Location header
                    $location = curl_getinfo($this->curl, CURLINFO_REDIRECT_URL);
                }

                if($location)
                {
                    $numRedirects += 1;
                    $response = $this->get($location, $numRedirects, $originalUri);
                }
                else
                {
                    $response = new LODResponse();
                    $response->target = $originalUri;

                    // not sure if this error code is appropriate...
                    $response->error = CURLE_GOT_NOTHING;

                    $response->errMsg = 'Got redirect but no location';
                }
            }
            // any other response
            else
            {
                $response = new LODResponse();
                $response->target = $originalUri;
                $response->payload = $raw_response;
                $response->status = $status;
                $response->type = $type;

                if(!$contentLocation)
                {
                    $contentLocation = $uri;
                }
                $response->contentLocation = $contentLocation;
            }
        }

        return $response;
    }
}
