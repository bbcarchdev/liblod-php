<?php

require_once(dirname(__FILE__) . '/lodinstance.php');
require_once(dirname(__FILE__) . '/lodresponse.php');

class LOD implements ArrayAccess
{
    /* The cURL handle used for fetches */
    public $curl;

    /* The HTTP user agent used in requests */
    public $userAgent = 'liblod/PHP';

    /* The HTTP "Accept" header used in requests */
    public $accept = 'text/turtle;q=0.95, application/rdf+xml;q=0.5';

    /* The most recently-fetched subject URI */
    protected $subject;

    /* The most recently-fetched document URL */
    protected $document;

    /* HTTP status from the most recent fetch */
    protected $status = 0;

    /* The error code from the most recent fetch */
    protected $error = 0;

    /* The error message from the most recent fetch */
    protected $errMsg = NULL;

    /* The RDF model (map from URIs to LODInstance objects) */
    protected $model = array();

    public function __construct()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
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

    // perform an HTTP GET and return a LODResponse
    private function http_get($uri)
    {
        curl_setopt($this->curl, CURLOPT_URL, $uri);

        $response = new LODResponse($this);
        $response->target = $uri;

        $raw_response = curl_exec($this->curl);

        if($raw_response === FALSE)
        {
            $response->error = curl_errno($this->curl);
            $response->errMsg = curl_error($this->curl);
        }
        else
        {
            $response->payload = $raw_response;
            $response->status = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
            $response->type = curl_getinfo($this->curl, CURLINFO_CONTENT_TYPE);
        }

        $this->status = $response->status;
        $this->error = $response->error;
        $this->errMsg = $response->errMsg;

        return $response;
    }

    /* Resolve a LOD URI, potentially fetching data;
    * returns a LODInstance or FALSE on hard error.
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
     */
    public function locate($uri)
    {
        $hasUri = array_key_exists($uri, $this->model);
        return ($hasUri ? $this->model[$uri] : FALSE);
    }

    /* Fetch data about a subject over HTTP (irrespective of
     * whether it already exists in the model) and process into
     * the model.
     * Returns a LODInstance or false on hard error.
     */
    public function fetch($uri)
    {
        $response = $this->http_get($uri);

        if($response->error)
        {
            return FALSE;
        }
        else
        {
            return $this->process($response);
        }
    }

    /* Process a LODResponse into the model */
    public function process(LODResponse $response)
    {
        $instance = new LODInstance($this, $response);
        $this->model[$response->target] = $instance;
        return $instance;
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
}
?>
