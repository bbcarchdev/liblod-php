<?php
/* Ideally, ArrayAccess and Iterator methods would be provided to allow idioms
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
require_once(dirname(__FILE__) . '/lod.php');
require_once(dirname(__FILE__) . '/lodresponse.php');

class LODInstance
{
	/* The LOD context we come from */
	protected $context;

	/* The subject URI of this instance */
	protected $uri;

	/* The actual RDF model (an array of triples in N3.js format) */
	protected $model;

    /* IDs of existing subject+predicate+object triples; this is to check
       whether a triple exists before adding it to the model */
    private $tripleIds = array();

	public function __construct(LOD $context, $uri, $internalRdfModel=array())
	{
        $this->context = $context;
        $this->uri = $uri;
        $this->model = $internalRdfModel;
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
				trigger_warning("The LODInstance::$name property is read-only", E_USER_WARNING);
				return;
		}
		$this->{$name} = $value;
	}

    /* Get a unique identifer for a triple */
    public function getTripleId($triple)
    {
        return $triple['subject'] . $triple['predicate'] . $triple['object'];
    }

    /* Add a triple to the model, but only if it's not an exact duplicate
       of an existing triple. */
    public function add($triple)
    {
        $tripleId = $this->getTripleId($triple);

        if(!(in_array($tripleId, $this->model)))
        {
            $this->model[] = $triple;
        }
    }

	/* Return true if the subject exists in the related context */
	public function exists()
	{
        $found = $this->context->locate($this->uri);
        return $found !== FALSE;
	}

	/* Return an instance representing the foaf:primaryTopic of the supplied
	 * instance, if one exists.
	 */
	public function primaryTopic()
	{
		/* Returns a new LODInstance */
	}
}
