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

/* Integration tests for LOD */
use bbcarchdev\liblod\LOD;

use PHPUnit\Framework\TestCase;

final class LODIntegrationTest extends TestCase
{
    function testGetSameAs()
    {
        $lod = new LOD();
        $uri = 'http://acropolis.org.uk/a75e5495087d4db89eccc6a52cc0e3a4#id';
        $lod->fetch($uri);

        $expected = array(
            'http://data.nytimes.com/N82665294351220674963',
            'http://data.nytimes.com/dench_judi_per',
            'http://dbpedia.org/resource/Judi_Dench',
            'http://rdf.freebase.com/ns/en.judi_dench',
            'http://rdf.freebase.com/ns/guid.9202a8c04000641f8000000000095614',
            'http://rdf.freebase.com/ns/m.0lpjn',
            'http://www.dbpedialite.org/things/85432#id',
            'http://www.wikidata.org/entity/Q28054'
        );

        $actual = $lod->getSameAs($uri);

        $this->assertEquals(count($expected), count($actual));

        foreach($expected as $expectedUri)
        {
            $this->assertContains($expectedUri, $actual);
        }
    }

    function testMultipleFetchesMerged()
    {
        // on first fetch for a search, a resource will only have a subset of
        // all the statements RES holds about it (in the search results summary);
        // on a subsequent fetch of the resource, it should have more statements
        // associated with it
        $lod = new LOD();

        $uri = 'http://acropolis.org.uk/?q=shakespeare';

        $resource = $lod->fetch($uri);

        // get the item for the first slot
        foreach($resource['olo:slot'] as $slot)
        {
            $slotUri = "$slot";
            break;
        }

        $itemUri = "{$lod[$slotUri]['olo:item']}";

        // resolve the slot URI without a fetch - this gets some minimal data
        $this->assertEquals(5, count($lod[$itemUri]->model),
                            'should be 5 triples for ' . $itemUri);

        // fetch additional data about the URI and check it's added to the model
        $lod->fetch($itemUri);
        $this->assertEquals(10, count($lod[$itemUri]->model),
                            'should be 10 triples for ' . $itemUri .
                            ' after merge');
    }

    function testGetGoodUri()
    {
        $lod = new LOD();

        $uri = 'http://acropolis.org.uk/a75e5495087d4db89eccc6a52cc0e3a4#id';

        $lod->fetch($uri);

        $this->assertEquals(200, $lod->status);
        $this->assertEquals(0, $lod->error);
        $this->assertEquals(NULL, $lod->errMsg);

        // check that we can resolve the fetched URI via the LOD instance and
        // that the RDF looks how we expect
        $instance = $lod->resolve($uri);

        $triples = $instance->model;

        foreach($triples as $triple)
        {
            $this->assertEquals(
                $uri,
                $triple->subject->value,
                'Triples should all be about Judi Dench (URI = ' .
                $uri . ') but one was about ' . $triple->subject->value
            );
        }
    }

    function testContentLocation()
    {
        $lod = new LOD();

        $uri = 'http://acropolis.org.uk/a75e5495087d4db89eccc6a52cc0e3a4';

        $lod->fetch($uri);

        $this->assertEquals($uri, $lod->subject);
        $this->assertEquals('/a75e5495087d4db89eccc6a52cc0e3a4.ttl', $lod->document);
    }

    function testGetGoodURIDifferentFormats()
    {
        // get Turtle
        $lod = new LOD();

        $turtleUri = 'http://acropolis.org.uk/a75e5495087d4db89eccc6a52cc0e3a4.ttl';

        $lod->fetch($turtleUri);

        $this->assertEquals(200, $lod->status);
        $this->assertEquals(0, $lod->error);
        $this->assertEquals(NULL, $lod->errMsg);

        // check we have the expected number of statements
        $instanceTurtle = $lod->resolve($turtleUri);

        // get RDF/XML
        $rdfxmlUri = 'http://acropolis.org.uk/a75e5495087d4db89eccc6a52cc0e3a4.rdf';

        $lod->fetch($rdfxmlUri);

        $this->assertEquals(200, $lod->status);
        $this->assertEquals(0, $lod->error);
        $this->assertEquals(NULL, $lod->errMsg);

        $instanceRdfxml = $lod->resolve($rdfxmlUri);

        // check we have the expected number of statements
        $turtleTriples = $instanceTurtle->model;
        $rdfxmlTriples = $instanceRdfxml->model;
        $this->assertEquals(3, count($turtleTriples));
        $this->assertEquals(3, count($rdfxmlTriples));

        // check that the two parsers produced the same output
        foreach($turtleTriples as $triple)
        {
            // ignore triples which refer to the RDF/XML or Turtle format
            // of the description document, as these only appear for the
            // format you requested (i.e. if you request Turtle, you'll only
            // get a dct:hasFormat triple and slot triples for the Turtle
            // representation)
            $badTriple = (strpos($triple->subject->value, $turtleUri) !== FALSE ||
                          strpos($triple->object->value, $turtleUri) !== FALSE ||
                          strpos($triple->subject->value, $rdfxmlUri) !== FALSE ||
                          strpos($triple->object->value, $rdfxmlUri) !== FALSE);

            if (!$badTriple)
            {
                $this->assertContains($triple, $rdfxmlTriples);
            }
        }
    }

    function testNotFoundURI()
    {
        $lod = new LOD();

        $uri = 'http://acropolis.org.uk/flukeydookeydoo';

        $lod->fetch($uri);

        $this->assertEquals(1, $lod->error);
    }

    function testHtmlAlternateLink()
    {
        $lod = new LOD();

        $uri = 'http://acropolis.org.uk/a75e5495087d4db89eccc6a52cc0e3a4.html';

        $lod->fetch($uri);

        $this->assertEquals(200, $lod->status);
        $this->assertEquals(0, $lod->error);
        $this->assertEquals(NULL, $lod->errMsg);

        // check that the originally-requested URI remains as the last-fetched URI
        $this->assertEquals($uri, $lod->subject);

        // check we have the expected number of statements
        $instance = $lod->resolve('http://acropolis.org.uk/a75e5495087d4db89eccc6a52cc0e3a4');
        $this->assertEquals(33, count($instance->model));
    }

    function testDbpediaLiteResolveUri()
    {
        // test that DBPediaLite URIs can be resolved
        $lod = new LOD();
        $instance = $lod->resolve('http://www.dbpedialite.org/things/22308#id');
        $this->assertEquals(10, count($instance->model));
    }

    function testDbpediaRedirectFollowed()
    {
        // test that redirects (as used by DBPedia) are followed correctly
        $lod = new LOD();
        $instance = $lod->resolve('http://dbpedia.org/resource/Oxford');
        $this->assertEquals('http://dbpedia.org/data/Oxford.ttl',
                            $lod->document,
                            'should store final redirect location as document');
    }
}
?>
