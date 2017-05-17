<?php
/**
 * RDF helper functions
 */
class Rdf
{
    /**
     * Convert an EasyRdf_Graph into an array of triples.
     *
     * $graph EasyRdf_Graph
     * $uri: if set, only return triples with $uri as a subject
     *
     * returns array of triples in N3.js format, e.g. each element looks like:
     * {
     *   "subject": "http://acropolis.org.uk/a75e5495087d4db89eccc6a52cc0e3a4#id",
     *   "predicate": "http://www.w3.org/2000/01/rdf-schema#label",
     *   "object": "'Judi Dench'@en-gb"
     * }
     * (see https://github.com/RubenVerborgh/N3.js#triple-representation)
     */
    public static function getTriples($graph, $uri=NULL)
    {
        $triples = array();

        foreach($graph->toRdfPhp() as $subjectUri => $properties)
        {
            if($uri && ($subjectUri !== $uri))
            {
                continue;
            }

            foreach($properties as $propertyUri => $objects)
            {
                foreach($objects as $object) {
                    if($object['type'] === 'uri')
                    {
                        $objectValue = $object['value'];
                    }
                    else
                    {
                        $objectValue = '"' . $object['value'] . '"';
                        if(array_key_exists('lang', $object))
                        {
                            $objectValue .= '@' . $object['lang'];
                        }
                        else if(array_key_exists('datatype', $object))
                        {
                            $objectValue .= '^^<' . $object['datatype'] . '>';
                        }
                    }

                    $triples[] = array(
                        'subject' => $subjectUri,
                        'predicate' => $propertyUri,
                        'object' => $objectValue
                    );
                }
            }
        }

        return $triples;
    }

    // these are adapted from the hardf source, which has a bug which prevents
    // the Util module from working correctly (syntax is PHP7 specific);
    // these functions are used to parse the various parts of a literal represented
    // using the N3.js triple format
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

    public static function getLiteralLanguage($literal)
    {
        $lang = '';

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
                $lang = $matches[1];
            }
        }
        return $lang;
    }
}
?>
