<?php
/* Integration tests for LOD
 *
 * Because we're using curl handles, we just test against the live
 * Acropolis, because we can't stub out the HTTP client for unit testing.
 *
 * Also note we can't force curl to throw a really bad error to test
 * LOD->error and LOD->errMsg.
 */
require_once(dirname(__FILE__) . '/../src/lod.php');

use PHPUnit\Framework\TestCase;

final class LODTest extends TestCase
{
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

        $this->assertEquals(71, count($triples));
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

        $this->assertEquals(404, $lod->status);
        $this->assertEquals(0, $lod->error);
        $this->assertEquals(NULL, $lod->errMsg);
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
        $this->assertEquals(17, count($instance->model));
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
        $this->assertEquals(190, count($instance->model));
   }
}
?>
