<?php
require_once(dirname(__FILE__) . '/lodinstance.php');
require_once(dirname(__FILE__) . '/httpclient.php');
require_once(dirname(__FILE__) . '/parser.php');
require_once(dirname(__FILE__) . '/rdf.php');

class LOD implements ArrayAccess
{
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

    public function __construct()
    {
        $this->httpClient = new HttpClient();
        $this->parser = new Parser();
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

            return $this->process($response);
        }
    }

    /* Process a LODResponse into the model */
    public function process(LODResponse $response)
    {
        // make a graph from the response
        $graph = $this->parser->parse($response->payload, $response->type);

        // add resources from the new graph to this LOD's graph
        foreach($graph->resources() as $subjectUri => $resource)
        {
            $this->index[$subjectUri] = new LODInstance($this, $resource);
        }

        // return the LODInstance for the originally-requested URI
        return $this->locate($response->target);
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
            case 'triples':
                return $this->triples();
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
                return TRUE;
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
        $inst = $this->locate($name);
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

    /* Get all the triples held by this LOD for each subject URI as an array */
    public function triples()
    {
        $triples = array();

        foreach($this->index as $subjectUri => $instance)
        {
            $triples += Rdf::getTriples($instance->model);
        }

        return $triples;
    }
}
?>
