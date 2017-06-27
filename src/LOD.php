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

use res\liblod\LODInstance;
use res\liblod\HttpClient;
use res\liblod\Parser;
use res\liblod\Rdf;

use \ArrayAccess;

// ArrayAccess implementation
trait LODArrayAccess
{
    public function offsetGet($name)
    {
        return $this->resolve($name);
    }

    public function offsetExists($name)
    {
        $inst = $this->offsetGet($name);
        return (is_object($inst) && $inst->exists);
    }

    /**
     * @codeCoverageIgnore
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function offsetSet($offset, $value)
    {
        trigger_error("LOD array members are read-only", E_USER_NOTICE);
    }

    /**
     * @codeCoverageIgnore
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function offsetUnset($offset)
    {
        trigger_error("LOD array members are read-only", E_USER_NOTICE);
    }
}

class LOD implements ArrayAccess
{
    use LODArrayAccess;

    /* Languages we prefer to get literals in (first in the list has highest
       priority) */
    public $languages = array('en-gb', 'en');

    /* The most recently-fetched URI */
    protected $subject;

    /* The most recently-fetched document URL (content-location or $subject
       if content-location not set) */
    protected $document;

    /* HTTP status from the most recent fetch */
    protected $status;

    /* The error code from the most recent fetch */
    protected $error;

    /* The error message from the most recent fetch */
    protected $errMsg;

    /* The RDF "index" (map from subject URIs to LODInstance objects) */
    protected $index = array();

    /* HTTP client */
    protected $httpClient;

    /* RDF parser */
    protected $parser;

    /* RDF processor */
    protected $rdf;

    /* RDF prefixes */
    public $prefixes = Rdf::COMMON_PREFIXES;

    public function __construct($httpClient=NULL, $parser=NULL, $rdf=NULL)
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

    /* Set an RDF prefix, which can be used in accessor strings on
       LODInstances */
    public function setPrefix($prefix, $uri)
    {
        $this->prefixes[$prefix] = $uri;
    }

    /* Resolve a LOD URI, potentially fetching data.
     * Returns a LODInstance or FALSE on hard error.
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

    /* Attempt to locate a subject within the context's model, but don't
     * try to fetch it if it's not present.
     * Returns a LODInstance or false if the URI doesn't exist in the model.
     */
    public function locate($uri)
    {
        $hasUri = array_key_exists($uri, $this->index);
        return ($hasUri ? $this->index[$uri] : FALSE);
    }

    /* Fetch data about a subject over HTTP (irrespective of
     * whether it already exists in the model) and process into
     * the model.
     * Returns a LODInstance or false on hard error.
     */
    public function fetch($uri)
    {
        $response = $this->httpClient->get($uri);
        $this->process($response);
        return $this->locate($uri);
    }

    /* Fetch multiple URIs; NB this doesn't return anything, but just
       sets up the graph quickly ready for querying later;
       $uris is an array of URIs to fetch;
       $this->status, $this->error and $this->errMsg are set from the
       last-fetched URI; returns TRUE if all responses were successful,
       FALSE otherwise */
    public function fetchAll($uris)
    {
        $responses = $this->httpClient->getAll($uris);

        foreach($responses as $response)
        {
            $this->process($response);
        }
    }

    /* Manually load some RDF into the model;
       type should be 'text/turtle' or 'application/rdf+xml' */
    public function loadRdf($rdf, $type)
    {
        // make a graph from the response
        $triples = $this->parser->parse($rdf, $type);

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
    }

    /* Process a LODResponse into the model; return the LODInstance, or FALSE
       if the fetch failed */
    private function process(LODResponse $response)
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
        $this->loadRdf($response->payload, $response->type);

        return TRUE;
    }

    /* Fetch an array of ?sameAs URIs which match the pattern
       ?sameAs owl:sameAs $uri */
    public function getSameAs($uri)
    {
        // iterate all statements for the LOD instance, looking for those with
        // subject === URI, predicate === owl:sameAs, object === object resource,
        // and return an array of the URIs of the object resources
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
    public function __get($name)
    {
        $hasProperty = property_exists(get_class($this), $name);
        return ($hasProperty ? $this->{$name} : NULL);
    }

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
                trigger_error("The LOD::$name property is read-only",
                              E_USER_WARNING);
        }
        $this->{$name} = $value;
    }

    /**
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
                trigger_error("The LOD::$name property is read-only",
                              E_USER_WARNING);
        }
        unset($this->{$name});
    }

    /**
     * @codeCoverageIgnore
     */
    public function __isset($name)
    {
        return isset($this->{$name});
    }
}
