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
    function testGetGoodURI()
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
        $this->assertEquals(366, count($instanceTurtle->model));

        // get RDF/XML
        $rdfxmlUri = 'http://acropolis.org.uk/a75e5495087d4db89eccc6a52cc0e3a4.rdf';

        $lod->fetch($rdfxmlUri);

        $this->assertEquals(200, $lod->status);
        $this->assertEquals(0, $lod->error);
        $this->assertEquals(NULL, $lod->errMsg);

        // check we have the expected number of statements
        $instanceRdfxml = $lod->resolve($rdfxmlUri);
        $this->assertEquals(366, count($instanceRdfxml->model));

        // check that the two parsers produced the same output
        foreach($instanceTurtle->model as $triple)
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
                $this->assertContains($triple, $instanceRdfxml->model);
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
}
?>
