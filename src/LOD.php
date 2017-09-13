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

namespace bbcarchdev\liblod;

use bbcarchdev\liblod\LODInstance;
use bbcarchdev\liblod\HttpClient;
use bbcarchdev\liblod\Parser;
use bbcarchdev\liblod\Rdf;

use \ArrayAccess;
use \Exception;

/**
 * RDF context, containing merged RDF from all of the URIs resolved against it.
 */
class LOD implements ArrayAccess
{
    use LODArrayAccess;

    /**
     * Languages we prefer to get literals in (first in the list has highest
     * priority).
     * @property array $languages
     */
    private $languages = array('en-gb', 'en');

    /**
     * The most recently-fetched URI.
     * @property string $subject
     */
    protected $subject;

    /**
     * The most recently-fetched document URL (content-location or URI
     * if content-location not set).
     * @property string $document
     */
    protected $document;

    /**
     * HTTP status from the most recent fetch.
     * @property int $status
     */
    protected $status;

    /**
     * The error code from the most recent fetch.
     * @property int $error
     */
    protected $error;

    /**
     * The error message from the most recent fetch.
     * @property string $errMsg
     */
    protected $errMsg;

    /**
     * The RDF "index" (map from subject URIs to LODInstance objects).
     * @property array $index
     */
    protected $index = array();

    /**
     * RDF prefix map.
     * @property array $prefixes Map from RDF prefixes to full URIs.
     */
    public $prefixes = Rdf::COMMON_PREFIXES;

    // HTTP client
    private $httpClient;

    // RDF parser
    private $parser;

    // RDF helper
    private $rdf;

    // Process a LODResponse into the model; return TRUE if it could be
    // processed and loaded, or FALSE if the fetch failed
    private function _process(LODResponse $response)
    {
        $this->status = $response->status;
        $this->error = $response->error;
        $this->errMsg = $response->errMsg;

        if($response->error)
        {
            return FALSE;
        }

        // save the subject and document URIs
        $this->subject = $response->target;
        $this->document = $response->contentLocation;

        // make a graph from the response
        return $this->loadRdf($response->payload, $response->type);
    }

    /**
     * Constructor.
     *
     * @param bbcarchdev\liblod\HttpClient $httpClient
     * @param bbcarchdev\liblod\Parser $parser
     * @param bbcarchdev\liblod\Rdf $rdf
     */
    public function __construct($httpClient = NULL, $parser = NULL, $rdf = NULL)
    {
        if(empty($httpClient))
        {
            $httpClient = new HttpClient();
        }

        if(empty($parser))
        {
            $parser = new Parser();
        }

        if(empty($rdf))
        {
            $rdf = new Rdf();
        }

        $this->httpClient = $httpClient;
        $this->parser = $parser;
        $this->rdf = $rdf;
    }

    /**
     * Set an RDF prefix, which can be used in query strings on
     * LODInstances created from this context.
     *
     * @param string $prefix For example, 'rdfs'.
     * @param string $uri Full URI the prefix maps to,
     * e.g. 'http://www.w3.org/2000/01/rdf-schema#'.
     */
    public function setPrefix($prefix, $uri)
    {
        $this->prefixes[$prefix] = $uri;
    }

    /**
     * Resolve a LOD URI, potentially fetching data.
     *
     * @param string $uri URI to resolve
     *
     * @return LODInstance|FALSE
     */
    public function resolve($uri)
    {
        $found = $this->locate($uri);
        if(!$found)
        {
            $found = $this->fetch($uri);
        }
        return $found;
    }

    /**
     * Attempt to locate a subject within the index, but don't
     * try to fetch it if it's not present.
     *
     * @param string $uri URI to locate
     *
     * @return LODInstance|FALSE Returns FALSE if the URI doesn't exist in the
     * context.
     */
    public function locate($uri)
    {
        $hasUri = array_key_exists($uri, $this->index);
        return ($hasUri ? $this->index[$uri] : FALSE);
    }

    /**
     * Fetch data about a subject over HTTP (irrespective of
     * whether it already exists in the index) and process into
     * the index.
     *
     * @param string $uri URI to fetch over HTTP
     *
     * @return LODInstance|FALSE Returns false if $uri can't be fetched, the
     * response can't be processed, or if the URI can't be located in the model
     * after the fetch.
     */
    public function fetch($uri)
    {
        $response = $this->httpClient->get($uri);

        $result = $this->_process($response);
        if(!$result)
        {
            return FALSE;
        }

        return $this->locate($uri);
    }

    /**
     * Fetch multiple URIs; NB this just sets up the index quickly ready for
     * querying later but is difficult to debug, as we just return a
     * single boolean regardless of where the failure occurred.
     *
     * $this->status, $this->error and $this->errMsg are set from the
     * last-fetched URI.
     *
     * @param array $uris Array of URIs to fetch
     *
     * @return TRUE if all responses were successful and processed, FALSE
     * otherwise
     */
    public function fetchAll($uris)
    {
        $responses = $this->httpClient->getAll($uris);

        $success = TRUE;
        foreach($responses as $response)
        {
            if($response->error > 0)
            {
                $success = FALSE;
            }

            $result = $this->_process($response);
            if(!$result)
            {
                $success = FALSE;
            }
        }

        return $success;
    }

    /**
     * Manually load some RDF into the index.
     *
     * @param string $rdf RDF to load
     * @param string $type Sshould be 'text/turtle' or 'application/rdf+xml'
     *
     * @return bool FALSE if RDF could not be loaded, TRUE otherwise
     */
    public function loadRdf($rdf, $type)
    {
        // make a graph from the response
        try
        {
            $triples = $this->parser->parse($rdf, $type);
        }
        catch (Exception $e)
        {
            return FALSE;
        }

        // add resources from the new graph to LODInstances in the index
        foreach($triples as $triple)
        {
            $subjectUri = $triple->subject->value;

            $instance = NULL;

            if (!isset($this->index[$subjectUri]))
            {
                $instance = new LODInstance($this, $subjectUri);
                $this->index[$subjectUri] = $instance;
            }

            $instance = $this->index[$subjectUri];
            $instance->add($triple);
        }

        return TRUE;
    }

    /**
     * Fetch an array of ?sameAs URIs which match the pattern
     * ?sameAs owl:sameAs $uri.
     *
     * @param string $uri
     *
     * @return array Array of matching URIs
     */
    public function getSameAs($uri)
    {
        // iterate all statements for the LOD instance, looking for those with
        // subject === URI, predicate === owl:sameAs, object === object
        // resource, and return an array of the URIs of the object resources
        $owlSameAsPred = $this->rdf->expandPrefix('owl:sameAs');

        $sameAsUris = array();

        foreach($this->index as $instance)
        {
            foreach($instance->model as $statement)
            {
                if($statement->object->value === $uri &&
                   $statement->predicate->value === $owlSameAsPred)
                {
                    $sameAsUris[] = $statement->subject->value;
                }
            }
        }

        return array_unique($sameAsUris);
    }

    /* Property accessors */
    /**
     * Magic method for getting properties.
     * @param string $name Name of property to get value for
     * @return mixed Value of property
     *
     * for $this->{$name}...
     * @SuppressWarnings controlCloseCurly
     */
    public function __get($name)
    {
        $hasProperty = property_exists(get_class($this), $name);
        return ($hasProperty ? $this->{$name} : NULL);
    }

    /**
     * Magic method for setting properties.
     * @param string $name Name of property to set value for
     * @param mixed $value Value to set for property
     *
     * because we trigger_error(), we don't need break...
     * @SuppressWarnings switchCaseNeedBreak
     * for $this->{$name}...
     * @SuppressWarnings controlCloseCurly
     */
    public function __set($name, $value)
    {
        switch($name)
        {
            case 'subject':
            case 'document':
            case 'status':
            case 'error':
            case 'errMsg':
            case 'index':
                trigger_error('The LOD->' . $name . ' property is read-only',
                              E_USER_WARNING);
            default:
                $this->{$name} = $value;
        }
    }

    /**
     * Unset a property.
     * @param string $name Name of property to unset.
     *
     * because we trigger_error(), we don't need break...
     * @SuppressWarnings switchCaseNeedBreak
     * @codeCoverageIgnore
     */
    public function __unset($name)
    {
        switch($name)
        {
            case 'subject':
            case 'document':
            case 'status':
            case 'error':
            case 'errMsg':
            case 'index':
                trigger_error('The LOD->' . $name . ' property is read-only',
                              E_USER_WARNING);
            default:
                unset($this->{$name});
        }
    }

    /**
     * Check whether a property is set.
     * @param string $name Name of property to check
     * @return bool
     *
     * @codeCoverageIgnore
     */
    public function __isset($name)
    {
        return isset($this->{$name});
    }
}

// ArrayAccess implementation
trait LODArrayAccess
{
    /**
     * For array access; passes control to bbcarchdev\liblod\LOD->resolve().
     * @param string $uri URI of LODInstance to get.
     * @return LODInstance|FALSE
     */
    public function offsetGet($uri)
    {
        return $this->resolve($uri);
    }

    /**
     * Check whether a LODInstance exists for a specific URI.
     * @param string $uri URI of LODInstance to test for.
     * @return bool
     */
    public function offsetExists($uri)
    {
        $inst = $this->offsetGet($uri);
        return (is_object($inst) && $inst->exists);
    }

    /**
     * @codeCoverageIgnore
     * @SuppressWarnings docBlocks
     * @SuppressWarnings checkUnusedFunctionParameters
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function offsetSet($offset, $value)
    {
        trigger_error('LOD array members are read-only', E_USER_NOTICE);
    }

    /**
     * @codeCoverageIgnore
     * @SuppressWarnings docBlocks
     * @SuppressWarnings checkUnusedFunctionParameters
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function offsetUnset($offset)
    {
        trigger_error('LOD array members are read-only', E_USER_NOTICE);
    }
}
