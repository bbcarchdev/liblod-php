<?php
require_once(dirname(__FILE__) . '/../vendor/autoload.php');
require_once(dirname(__FILE__) . '/lod.php');
require_once(dirname(__FILE__) . '/rdf.php');
require_once(dirname(__FILE__) . '/lodresponse.php');

/**
 * Wrapper for an EasyRdf_Resource.
 *
 * The wrapped EasyRdf_Resource is accessible via:
 *   $instance->model
 *
 * The raw triples for the resource represented by this instance are
 * accessible via:
 *   $instance->triples
 *
 * Ideally, ArrayAccess and Iterator methods would be provided to allow idioms
 * such as:
 *
 * * possibly returns an encapsulated array which can be elided to
 *   a string via __toString():-
 *
 *      $labels = $inst['rdfs:label'];
 *
 * * iterate all of the statements relating to the subject:
 *
 *      foreach($inst as $triple)
 *      {
 *          echo "this triple is " . $triple . "\n";
 *      }
 */
class LODInstance
{
    /* The LOD context we come from */
    protected $context;

    /* The subject URI of this instance */
    protected $uri;

    /* The wrapped EasyRdf_Resource */
    protected $model;

    public function __construct(LOD $context, EasyRdf_Resource $resource)
    {
        $this->context = $context;
        $this->model = $resource;
        $this->uri = $resource->getUri();
    }

    public function __get($name)
    {
        switch($name)
        {
            case 'exists':
                return $this->exists();
            case 'primaryTopic':
                return $this->primaryTopic();
            case 'uri':
                return $this->uri;
            case 'model':
                return $this->model;
            case 'triples':
                return $this->triples();
        }
    }

    public function __set($name, $value)
    {
        switch($name)
        {
            case 'exists':
            case 'primaryTopic':
            case 'uri':
            case 'model':
            case 'triples':
                trigger_warning("The LODInstance::$name property is read-only", E_USER_WARNING);
                return;
        }
        $this->{$name} = $value;
    }

    /* Return true if the subject exists in the related context */
    public function exists()
    {
        $found = $this->context->locate($this->uri);
        return $found !== FALSE;
    }

    /* Return a LODInstance representing the foaf:primaryTopic of the supplied
     * instance, if one exists.
     */
    public function primaryTopic()
    {
        // find the foaf:primaryTopic URI for this instance

        // try to retrieve the LODInstance for that URI from the context
    }

    /**
     * Dump the EasyRdf_Resource content as an array of triples in N3.js
     * format, including only the triples matching the URI of this instance.
     */
    public function triples()
    {
        return Rdf::getTriples($this->model->getGraph(), $this->uri);
    }
}
