<?php
namespace res\liblod;

require_once(dirname(__FILE__) . '/../vendor/autoload.php');

use res\liblod\LOD;

/**
 * RDF helper functions
 */
class Rdf
{
    const COMMON_PREFIXES = array(
        'dcmitype' => 'http://purl.org/dc/dcmitype/',
        'dct' => 'http://purl.org/dc/terms/',
        'dcterms' => 'http://purl.org/dc/terms/',
        'foaf' => 'http://xmlns.com/foaf/0.1/',
        'owl' => 'http://www.w3.org/2002/07/owl#',
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
        'schema' => 'http://schema.org/',
        'skos' => 'http://www.w3.org/2004/02/skos/core#',
        'void' => 'http://rdfs.org/ns/void#',
        'xsd' => 'http://www.w3.org/2001/XMLSchema#'
    );

    // if $predicate is in the form <prefix>:<term>, expand into a full URI
    // using $prefixes (in the same format as PREFIXES)
    public static function expandPrefix($predicate, $prefixes)
    {
        if($prefixes === NULL)
        {
            $prefixes = self::COMMON_PREFIXES;
        }

        if(substr($predicate, 0, 4) !== 'http')
        {
            $parts = explode(':', $predicate);
            $prefix = $parts[0];

            if(array_key_exists($prefix, $prefixes))
            {
                return $prefixes[$prefix] . $parts[1];
            }
        }
        return $predicate;
    }

    /**
     * Convert an EasyRdf_Graph into an array of triples.
     *
     * $graph EasyRdf_Graph
     * $uri: if set, only return triples with $uri as a subject
     *
     * returns array of LODTriples
     */
    public static function getTriples($graph, $uri=NULL)
    {
        $triples = array();

        // flatten out the PHP RDF produced by EasyRdf_Graph
        foreach($graph->toRdfPhp() as $subjectUri => $properties)
        {
            if($uri && ($subjectUri !== $uri))
            {
                continue;
            }

            foreach($properties as $propertyUri => $objects)
            {
                // see LODStatement->__construct() for format of $object
                foreach($objects as $object)
                {
                    $triples[] = new LODStatement(
                        $subjectUri,
                        $propertyUri,
                        $object
                    );
                }
            }
        }

        return $triples;
    }

    // these are adapted from the hardf source, which has a bug which prevents
    // the Util module from working correctly (syntax is PHP7 specific);
    // these functions are used to parse the various parts of a literal represented
    // using the N3.js triple format, as returned by the hardf Turtle parser
    public static function isLiteral($term)
    {
        return $term && substr($term, 0, 1) === '"';
    }

    public static function getLiteralValue($literal)
    {
        // remove the leading "
        $value = substr($literal, 1);

        // remove the part of the string from the character before the last " to the
        // end
        $lastQuotePos = strrpos($literal, '"');
        $value = substr($value, 0, $lastQuotePos - 1);

        return $value;
    }

    // returns array('lang' => 'lang string', 'datatype' => '...datatype...')
    public static function getLiteralLanguageAndDatatype($literal)
    {
        $language = NULL;
        $datatype = NULL;

        // get the part of the literal after the last "
        $lastQuotePos = strrpos($literal, '"');

        // check that we have something after the last "
        if($lastQuotePos < strlen($literal) - 1)
        {
            $lastPart = substr($literal, $lastQuotePos + 1);

            // get the part after the '@'; note that a literal shouldn't have a
            // type and a language
            $matches = array();
            if(preg_match('|@([^@]+)|', $lastPart, $matches))
            {
                $language = $matches[1];
            }
            else if (preg_match('|\^\^([^\^]+)|', $lastPart, $matches))
            {
                $datatype = $matches[1];
                $datatype = ltrim($datatype, '<');
                $datatype = rtrim($datatype, '>');
            }
        }
        return array(
            'lang' => $language,
            'datatype' => $datatype
        );
    }
}
?>
