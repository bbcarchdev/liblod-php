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

        $triples = $instance->triples;

        foreach($triples as $triple)
        {
            $this->assertEquals(
                $uri,
                $triple['subject'],
                'Triples should all be about Judi Dench (URI = ' .
                $uri . ') but was about ' . $triple['subject']
            );
        }

        $this->assertEquals(71, count($triples));

        $label = $instance->model->getLiteral('rdfs:label', 'en-gb');
        $this->assertEquals('Judi Dench', $label, 'Label should be "Judi Dench"');
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
        $this->assertEquals(3, count($instanceTurtle->triples));

        // get RDF/XML
        $rdfxmlUri = 'http://acropolis.org.uk/a75e5495087d4db89eccc6a52cc0e3a4.rdf';

        $lod->fetch($rdfxmlUri);

        $this->assertEquals(200, $lod->status);
        $this->assertEquals(0, $lod->error);
        $this->assertEquals(NULL, $lod->errMsg);

        // check we have the expected number of statements
        $instanceRdfxml = $lod->resolve($rdfxmlUri);
        $this->assertEquals(3, count($instanceRdfxml->triples));

        // check that the two parsers produced the same output
        $turtleTriples = $instanceTurtle->triples;
        $rdfxmlTriples = $instanceRdfxml->triples;
        foreach($turtleTriples as $triple)
        {
            // ignore triples which refer to the RDF/XML or Turtle format
            // of the description document, as these only appear for the
            // format you requested (i.e. if you request Turtle, you'll only
            // get a dct:hasFormat triple and slot triples for the Turtle
            // representation)
            $badTriple = (strpos($triple['subject'], $turtleUri) !== FALSE ||
                          strpos($triple['object'], $turtleUri) !== FALSE ||
                          strpos($triple['subject'], $rdfxmlUri) !== FALSE ||
                          strpos($triple['object'], $rdfxmlUri) !== FALSE);

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
        $this->assertEquals(17, count($instance->triples));
    }

    function testDbpediaLiteResolveUri()
    {
        // test that DBPediaLite URIs can be resolved
        $lod = new LOD();
        $instance = $lod->resolve('http://www.dbpedialite.org/things/22308#id');
        $this->assertEquals(10, count($instance->triples));
    }

    function testDbpediaRedirectFollowed()
    {
        // test that redirects (as used by DBPedia) are followed correctly
        $lod = new LOD();
        $instance = $lod->resolve('http://dbpedia.org/resource/Oxford');
        $this->assertEquals(190, count($instance->triples));
   }
}
?>
