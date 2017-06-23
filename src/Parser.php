<?php
namespace res\liblod;

use res\liblod\Rdf;
use res\liblod\LODResource;
use res\liblod\LODStatement;

use pietercolpaert\hardf\N3Parser;
use \EasyRdf_Parser_RdfXml;
use \EasyRdf_Graph;

/*
 * Wrapper round hardf and EasyRDF parsers
 * (hardf Turtle parser is much faster than EasyRDF but hardf doesn't have
 * an RDF/XML parser)
 */
class Parser
{
    // $rdf is a string of RDF to parse
    // $type is the mime type of the response being parsed; one of
    // text/turtle, application/rdf+xml
    //
    // returns EasyRdf_Graph
    public function parse($rdf, $type)
    {
        $triplesOut = array();

        if(preg_match('|^text/turtle|', $type))
        {
            // hardf uses the N3.js triple format
            $parser = new N3Parser(array());
            $triples = $parser->parse($rdf);

            if(count($triples) === 0)
            {
                echo "No triples could be parsed out of that there Turtle RDF\n\n";
                echo $rdf . "\n";
            }

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
            $parser = new EasyRdf_Parser_RdfXml();
            $parser->parse($graph, $rdf, 'rdfxml', '');
            $triplesOut = Rdf::getTriples($graph);
        }
        else
        {
            trigger_error('No parser for content type ' . $type);
        }

        return $triplesOut;
    }
}
