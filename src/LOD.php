<?php
namespace res\liblod;

use res\liblod\LODInstance;
use res\liblod\HttpClient;
use res\liblod\Parser;
use res\liblod\Rdf;

use \ArrayAccess;

class LOD implements ArrayAccess
{
    /* Languages we prefer to get literals in (first in the list has highest
       priority) */
    public $languages = array('en-gb', 'en');

    /* The most recently-fetched URI */
    protected $subject;

    /* The most recently-fetched document URL (content-location or $subject
       if content-location not set) */
    protected $document;

    /* HTTP status from the most recent fetch */
    protected $status = 0;

    /* The error code from the most recent fetch */
    protected $error = 0;

    /* The error message from the most recent fetch */
    protected $errMsg = NULL;

    /* The RDF "index" (map from subject URIs to LODInstance objects) */
    protected $index = array();

    /* HTTP client */
    protected $httpClient;

    /* RDF parser */
    protected $parser;

    /* RDF prefixes */
    public $prefixes = Rdf::COMMON_PREFIXES;

    public function __construct()
    {
        $this->httpClient = new HttpClient();
        $this->parser = new Parser();
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

            if (isset($this->index[$subjectUri]))
            {
                $instance = $this->index[$subjectUri];
            }
            else
            {
                $instance = new LODInstance($this, $subjectUri);
                $this->index[$subjectUri] = $instance;
            }

            $instance->add($triple);
        }
    }

    /* Process a LODResponse into the model; return the LODInstance, or FALSE
       if the fetch failed */
    public function process(LODResponse $response)
    {
        $this->status = $response->status;
        $this->error = $response->error;
        $this->errMsg = $response->errMsg;

        if($response->error)
        {
            return FALSE;
        }
        else
        {
            // save the subject and document URIs
            $this->subject = $response->target;
            $this->document = $response->contentLocation;

            if(empty($response))
            {
                return FALSE;
            }
        }

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
        $owlSameAsPred = Rdf::expandPrefix('owl:sameAs');

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
        switch($name)
        {
            case 'subject':
                return $this->subject;
            case 'document':
                return $this->document;
            case 'status':
                return $this->status;
            case 'error':
                return $this->error;
            case 'errMsg':
                return $this->errMsg;
            case 'index':
                return $this->index;
            case 'languages':
                return $this->languages;
            case 'prefixes':
                return $this->prefixes;
        }
        return null;
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
                trigger_warning("The LOD::$name property is read-only",
                                E_USER_WARNING);
                return;
        }
        $this->{$name} = $value;
    }

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
                trigger_warning("The LOD::$name property is read-only",
                                E_USER_WARNING);
                return;
        }
        unset($this->{$name});
    }

    public function __isset($name)
    {
        switch($name)
        {
            case 'subject':
            case 'document':
            case 'status':
            case 'error':
            case 'errMsg':
            case 'index':
                return isset($this->{$name});
        }
        return FALSE;
    }

    /* ArrayAccess methods */
    public function offsetGet($name)
    {
        return $this->resolve($name);
    }

    public function offsetExists($name)
    {
        $inst = $this->offsetGet($name);
        return (is_object($inst) && $inst->exists);
    }

    public function offsetSet($name, $value)
    {
        trigger_error("LOD array members are read-only", E_USER_NOTICE);
    }

    public function offsetUnset($name)
    {
        trigger_error("LOD array members are read-only", E_USER_NOTICE);
    }

    /**
     * Create a LOD statement, using the prefixes currently set on this
     * context.
     * See the LODStatement (rdf.php) constructor for details of the arguments.
     */
    public function createStatement($subject, $predicate, $object)
    {
        return new LODStatement($subject, $predicate, $object, $this->prefixes);
    }
}
