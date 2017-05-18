<?php
require_once(dirname(__FILE__) . '/../vendor/autoload.php');
require_once(dirname(__FILE__) . '/../src/lod.php');
require_once(dirname(__FILE__) . '/../src/rdf.php');
require_once(dirname(__FILE__) . '/../src/lodinstance.php');

use PHPUnit\Framework\TestCase;

final class LODInstanceTest extends TestCase
{
    private $testResource;
    private $testUri = 'http://foo.bar/';

    function setUp()
    {
        $graph = new EasyRdf_Graph();

        $testResource = $graph->resource($this->testUri);
        $testResource->addResource('foaf:page', 'http://foo.bar/page1');
        $testResource->addResource('foaf:page', 'http://foo.bar/page2');
        $testResource->addResource('rdfs:seeAlso', 'http://foo.bar/page3');
        $testResource->addLiteral('rdfs:label', 'Yoinch Chettner', 'en-gb');

        $this->testResource = $testResource;
    }

    function testMerge()
    {
        $instance = new LODInstance(new LOD(), $this->testUri);
        $instance->merge($this->testResource);

        $this->assertEquals(4, count($instance->triples));
    }

    function testFilter()
    {
        $instance = new LODInstance(new LOD(), $this->testUri, $this->testResource);

        // basic filter
        $filteredInstance = $instance->filter('foaf:page');
        $this->assertEquals(2, count($filteredInstance->triples));

        $expanded = Rdf::expandPrefix('foaf:page', Rdf::PREFIXES);
        foreach($filteredInstance->triples as $triple)
        {
            $this->assertEquals($expanded, $triple['predicate']);
        }
    }

    function testIteration()
    {
        $instance = new LODInstance(new LOD(), $this->testUri, $this->testResource);

        $expectedValues = array(
            'http://foo.bar/page1',
            'http://foo.bar/page2',
            'http://foo.bar/page3',
            'Yoinch Chettner'
        );

        $actualValues = array();
        foreach($instance as $triple)
        {
            $actualValues[] = $triple['object']['value'];
        }

        $this->assertEquals($expectedValues, $actualValues);
    }

    function testArrayAccess()
    {
        $instance = new LODInstance(new LOD(), $this->testUri, $this->testResource);

        // single predicate via offsetGet
        $filteredInstance1 = $instance['foaf:page'];
        $this->assertEquals(2, count($filteredInstance1->triples));
        $expanded = Rdf::expandPrefix('foaf:page', Rdf::PREFIXES);
        foreach($filteredInstance1->triples as $triple)
        {
            $this->assertEquals($expanded, $triple['predicate']);
        }

        // multiple predicates via offsetGet
        $filteredInstance2 = $instance['foaf:page,rdfs:seeAlso'];
        $this->assertEquals(3, count($filteredInstance2->triples));

        // key existence via offsetExists
        $this->assertEquals(FALSE, isset($instance['fo:po']));
        $this->assertEquals(TRUE, isset($instance['foaf:page']));
    }
}
?>
