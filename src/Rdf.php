<?php
/*
 * Copyright 2017 BBC
 *
 * Author: Elliot Smith <elliot.smith@bbc.co.uk>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace res\liblod;

use \Exception;

use EasyRdf_Graph;
use EasyRdf_Parser_Ntriples;
use EasyRdf_Serialiser_Turtle;
use EasyRdf_Namespace;

use res\liblod\LOD;

/**
 * RDF helper functions
 */
class Rdf
{
    const COMMON_PREFIXES = array(
        'bibo' => 'http://purl.org/ontology/bibo/',
        'cc' => 'http://creativecommons.org/ns#',
        'crm' => 'http://www.cidoc-crm.org/cidoc-crm/',
        'dcmitype' => 'http://purl.org/dc/dcmitype/',
        'dc' => 'http://purl.org/dc/terms/',
        'dct' => 'http://purl.org/dc/terms/',
        'dcterms' => 'http://purl.org/dc/terms/',
        'exif' => 'http://www.w3.org/2003/12/exif/ns#',
        'foaf' => 'http://xmlns.com/foaf/0.1/',
        'formats' => 'http://www.w3.org/ns/formats/',
        'frbr' => 'http://purl.org/vocab/frbr/core#',
        'geo' => 'http://www.w3.org/2003/01/geo/wgs84_pos#',
        'lio' => 'http://purl.org/net/lio#',
        'mrss' => 'http://search.yahoo.com/mrss/',
        'oa' => 'http://www.w3.org/ns/oa#',
        'odrl' => 'http://www.w3.org/ns/odrl/2/',
        'olo' => 'http://purl.org/ontology/olo/core#',
        'owl' => 'http://www.w3.org/2002/07/owl#',
        'po' => 'http://purl.org/ontology/po/',
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
        'schema' => 'http://schema.org/',
        'sioc' => 'http://rdfs.org/sioc/services#',
        'skos' => 'http://www.w3.org/2004/02/skos/core#',
        'vcard' => 'http://www.w3.org/vcard-rdf/3.0#',
        'void' => 'http://rdfs.org/ns/void#',
        'wdrs' => 'http://www.w3.org/2007/05/powder-s#',
        'xhtml' => 'http://www.w3.org/1999/xhtml/vocab#',
        'xsd' => 'http://www.w3.org/2001/XMLSchema#'
    );

    // if $predicate is in the form <prefix>:<term>, expand into a full URI
    // using $prefixes (in the same format as PREFIXES)
    public function expandPrefix($predicate, $prefixes=NULL)
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
     *
     * returns array of LODStatements
     */
    public function getTriples($graph)
    {
        $triples = array();

        // flatten out the PHP RDF produced by EasyRdf_Graph
        foreach($graph->toRdfPhp() as $subjectUri => $properties)
        {
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

    /**
     * Convert a LOD or LODInstance to RDF/Turtle.
     *
     * for EasyRdf_Namespace::set()...
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function toTurtle($lodORlodinstance, $prefixes=Rdf::COMMON_PREFIXES)
    {
        $statements = NULL;

        if($lodORlodinstance instanceof LOD)
        {
            $statements = array();
            foreach($lodORlodinstance->index as $lodinstance)
            {
                $statements = array_merge($statements, $lodinstance->model);
            }
        }
        else if($lodORlodinstance instanceof LODInstance)
        {
            $statements = $lodORlodinstance->model;
        }

        $rawNtriples = '';
        foreach($statements as $statement)
        {
            $rawNtriples .= "$statement .\n";
        }

        foreach($prefixes as $prefix => $fullUri)
        {
            EasyRdf_Namespace::set($prefix, $fullUri);
        }

        $graph = new EasyRdf_Graph();
        $parser = new EasyRdf_Parser_Ntriples();
        $parser->parse($graph, $rawNtriples, 'ntriples', '');

        $serialiser = new EasyRdf_Serialiser_Turtle();
        return $serialiser->serialise($graph, 'turtle');
    }

    // these are adapted from the hardf source, which has a bug which prevents
    // the Util module from working correctly (syntax is PHP7 specific);
    // these functions are used to parse the various parts of a literal represented
    // using the N3.js triple format, as returned by the hardf Turtle parser
    public function isLiteral($term)
    {
        return $term && substr($term, 0, 1) === '"';
    }

    public function getLiteralValue($literal)
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
    public function getLiteralLanguageAndDatatype($literal)
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
