<?php
/* Depending on the internal RDF implementation, this might reasonably
 * be a subclass of some kind of RDFResource class which relates to
 * a subject of a set of triples. Ideally, ArrayAccess and Iterator
 * methods would be provided to allow idioms such as:
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
require_once(dirname(__FILE__) . '/parser.php');

class LODInstance
{
	/* The LOD context we come from */
	protected $context;

	/* The subject URI of this instance */
	protected $uri;

	/* The actual RDF model */
	protected $model;

	public function __construct(LOD $context, LODResponse $response)
	{
        $this->context = $context;
        $this->uri = $response->target;

        $parser = new Parser();

        $this->model = $parser->parse($response->payload, $response->type);
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
