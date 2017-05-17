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

    public function __construct(LOD $context, $uri, EasyRdf_Resource $resource=NULL)
    {
        $this->context = $context;
        $this->uri = $uri;

        if($resource === NULL)
        {
            $resource = new EasyRdf_Resource($this->uri, new EasyRdf_Graph());
        }

        $this->model = $resource;
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

    /**
     * Merge another resource with this instance, providing the resource being
     * merged has the same URI as this instance.
     */
    public function merge(EasyRdf_Resource $resource)
    {
        // because EasyRdf doesn't seem to correctly store properties on the
        // resource, manually extract them from the resource's graph using
        // the resource URI as a filter
        $triples = Rdf::getTriples($resource->getGraph(), $resource->getUri());

        foreach($triples as $triple)
        {
            $propertyUri = $triple['predicate'];
            $object = $triple['object'];
            $this->model->add($propertyUri, $object);
        }
    }

    /* Return true if the subject exists in the related context */
    public function exists()
    {
        $found = $this->context->locate($this->uri);
        return $found !== FALSE;
    }

    /** TODO
     * Return a LODInstance representing the foaf:primaryTopic of this
     * instance, if one exists.
     */
    public function primaryTopic()
    {
        // find the foaf:primaryTopic URI for this instance

        // try to retrieve the LODInstance for that URI from the context
    }

    /**
     * Dump the EasyRdf_Resource content as an array of triples,
     * including only the triples matching the URI of this instance.
     */
    public function triples()
    {
        return Rdf::getTriples($this->model->getGraph(), $this->uri);
    }
}
?>
