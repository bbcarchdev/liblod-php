<?php
require_once(dirname(__FILE__) . '/../vendor/autoload.php');

use pietercolpaert\hardf\N3Parser;

/*
 * Wrapper round hardf and EasyRDF parsers
 * (hardf Turtle parser is much faster than EasyRDF but hardf doesn't have
 * an RDF/XML parser)
 */
class Parser
{
    public function __construct()
    {
        $this->turtleParser = new N3Parser(array());
        $this->rdfxmlParser = new EasyRdf_Parser_RdfXml();
    }

    // $rdf is a string of RDF to parse
    // $type is the mime type of the response being parsed
    // returns array of triples using the syntax defined at
    // https://github.com/RubenVerborgh/N3.js#triple-representation
    public function parse($rdf, $type)
    {
        $triples = [];

        if(preg_match('|^text\/turtle|', $type))
        {
            // hardf uses the N3.js triple format
            $triples = $this->turtleParser->parse($rdf);
        }
        else if(preg_match('|^application\/rdf\+xml|', $type))
        {
            $graph = new EasyRdf_Graph();
            $this->rdfxmlParser->parse($graph, $rdf, 'rdfxml', '');

            // EasyRdf doesn't use the N3.js triple format, so we have
            // to do the conversion
            foreach($graph->toRdfPhp() as $subjectUri => $properties)
            {
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
                            if(array_key_exists('datatype', $object))
                            {
                                $objectValue .= '^^<' . $object['datatype'] . '>';
                            }
                        }

                        $triples[] = array(
                            'subject' => $subjectUri,
                            'predicate' => $propertyUri,
                            'object' => $objectValue,
                            'graph' => ''
                        );
                    }
                }
            }
        }
        else
        {
            trigger_error('No parser for content type ' . $type);
        }

        return $triples;
    }
}
?>
