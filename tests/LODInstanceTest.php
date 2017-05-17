<?php
require_once(dirname(__FILE__) . '/../vendor/autoload.php');
require_once(dirname(__FILE__) . '/../src/lod.php');
require_once(dirname(__FILE__) . '/../src/rdf.php');
require_once(dirname(__FILE__) . '/../src/lodinstance.php');

use PHPUnit\Framework\TestCase;

final class LODInstanceTest extends TestCase
{
    function testMerge()
    {
        $graph = new EasyRdf_Graph();

        $uri = 'http://foo.bar/';

        $testResource = $graph->resource($uri);
        $testResource->addResource('foaf:page', 'http://foo.bar/page1');
        $testResource->addResource('foaf:page', 'http://foo.bar/page2');
        $testResource->addResource('rdfs:seeAlso', 'http://foo.bar/page3');
        $testResource->addLiteral('rdfs:label', 'Yoinch Chettner', 'en-gb');

        $instance = new LODInstance(new LOD(), $uri);
        $instance->merge($testResource);

        $this->assertEquals(4, count($instance->triples));
    }
}
?>
