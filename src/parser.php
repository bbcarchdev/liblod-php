<?php
require_once(dirname(__FILE__) . '/../vendor/autoload.php');
require_once(dirname(__FILE__) . '/rdf.php');

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
    //
    // returns EasyRdf_Graph
    public function parse($rdf, $type)
    {
        $triplesOut = array();

        if(preg_match('|^text/turtle|', $type))
        {
            // hardf uses the N3.js triple format
            // so we have to convert it to EasyRdf triples;
            // note that EasyRdf doesn't support specifying the datatypes for
            // a literal when adding one to a graph
            $triples = $this->turtleParser->parse($rdf);

            foreach($triples as $triple)
            {
                $subject = new LODResource($triple['subject']);
                $predicate = new LODResource($triple['predicate']);
                $object = $triple['object'];

                if(Rdf::isLiteral($object))
                {
                    $value = Rdf::getLiteralValue($object);
                    $languageAndType = Rdf::getLiteralLanguageAndDatatype($object);
                    $obj = new LODLiteral($value, $languageAndType);
                }
                else
                {
                    $obj = new LODResource($object);
                }

                $triplesOut[] = new LODStatement($subject, $predicate, $obj);
            }
        }
        else if(preg_match('|^application/rdf\+xml|', $type))
        {
            $graph = new EasyRdf_Graph();
            $this->rdfxmlParser->parse($graph, $rdf, 'rdfxml', '');
            $triplesOut = Rdf::getTriples($graph);
        }
        else
        {
            trigger_error('No parser for content type ' . $type);
        }

        return $triplesOut;
    }
}
?>
