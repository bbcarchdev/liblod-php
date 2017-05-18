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
 *   $instance->model
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
class LODInstance implements ArrayAccess, Iterator
{
    /* The LOD context we come from */
    protected $context;

    /* The subject URI of this instance */
    protected $uri;

    /* Array of LODStatement objects */
    protected $model;

    /* Generated keys of statements in the model (used to prevent
       duplicates */
    protected $statementKeys = array();

    // for the iterator
    private $position = 0;

    // This is TRUE if this LODInstance was created by filtering another
    // LODInstance; this affects how the LODInstance displays: if it's TRUE,
    // the __toString() method returns the object value of the first triple;
    // if FALSE, it returns the URI of the LODInstance
    private $fromFilter;

    public function __construct(LOD $context, $uri, $model=array(), $fromFilter=FALSE)
    {
        $this->context = $context;
        $this->uri = $uri;
        $this->model = $model;
        $this->fromFilter = $fromFilter;
    }

    public function __get($name)
    {
        switch($name)
        {
            case 'uri':
                return $this->uri;
            case 'model':
                return $this->model;
            case 'exists':
                return $this->exists();
            case 'primaryTopic':
                return $this->primaryTopic();
        }
    }

    public function __set($name, $value)
    {
        switch($name)
        {
            case 'uri':
            case 'model':
            case 'exists':
            case 'primaryTopic':
                trigger_warning("The LODInstance::$name property is read-only", E_USER_WARNING);
                return;
        }
        $this->{$name} = $value;
    }

    /**
     * Merge another resource with this instance, providing the resource being
     * merged has the same URI as this instance.
     */
    public function merge($statements)
    {
        foreach($statements as $statement)
        {
            $this->add($statement);
        }
    }

    /**
     * Add a LODStatement, but only if the same subject+predicate+object isn't
     * already in the model.
     */
    public function add($statement)
    {
        $key = $statement->getKey();
        if(!in_array($key, $this->statementKeys))
        {
            $this->model[] = $statement;
            $this->statementKeys[] = $key;
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
     * Create a new LODInstance containing a subset of the triples
     * in this one whose predicates match the provided query.
     *
     * $query is a string of RDF predicates, which can use short-hand terms for
     * prefixes (e.g. 'rdfs:seeAlso') or full URIs
     * (e.g. 'http://schema.org/about). If using prefixes, these should be
     * set on the LOD context for this LODInstance.
     *
     * Either a single predicate or multiple (comma-separated) predicates can be
     * specified, e.g.'dcterms:title,rdfs:label'.
     *
     * When matching triples, if the triple is a literal with a 'lang'
     * specifier, the 'lang' is compared to the languages set on the LOD
     * context; only literals with matching language (or no language) will be
     * included in the output LODInstance.
     *
     * NB this is the method behind the array accessor methods.
     *
     * Returns a LODInstance containing matching triples.
     */
    public function filter($query)
    {
        $prefixes = $this->context->prefixes;

        // parse and expand the query string
        $predicates = explode(',', $query);
        foreach($predicates as $index => $predicate)
        {
            $predicates[$index] = Rdf::expandPrefix(trim($predicate), $prefixes);
        }

        $languages = $this->context->languages;

        // get triples which match the query
        $fn = function($item) use($predicates, $languages)
        {
            $predicateMatches = in_array($item->predicate->value, $predicates);

            // if the object has no 'lang' key, we can't compare languages,
            // so we just assume the language is OK
            $langOK = TRUE;
            if(is_a($item->object, 'LODLiteral') && ($item->object->language !== NULL))
            {
                $langOK = in_array($item->object->language, $languages);
            }

            return $predicateMatches && $langOK;
        };

        $filtered = array_filter($this->model, $fn);

        // create a new LODInstance with the filtered triples and
        // $fromFilter=TRUE
        $instance = new LODInstance($this->context, $this->uri, $filtered, TRUE);

        return $instance;
    }

    // ArrayAccess implementation

    // an offset is assumed to exist if the query returns a LODInstance
    // with at least one matching LODStatement in its model
    public function offsetExists($query)
    {
        $instance = $this->offsetGet($query);
        return count($instance->model) > 0;
    }

    public function offsetGet($query)
    {
        return $this->filter($query);
    }

    public function offsetSet($query, $value)
    {
        trigger_error("LODInstance array members are read-only", E_USER_NOTICE);
    }

    public function offsetUnset($query)
    {
        trigger_error("LODInstance array members are read-only", E_USER_NOTICE);
    }

    // Iterator implementation
    public function current()
    {
        // TODO if instance is filtered, return the statement's object as
        // a LODTerm; otherwise return the full LODStatement
        return $this->model[$this->position];
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        ++$this->position;
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function valid()
    {
        return isset($this->model[$this->position]);
    }
}
?>
