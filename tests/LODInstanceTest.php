<?php
use res\liblod\LOD;
use res\liblod\LODInstance;
use res\liblod\Rdf;
use res\liblod\LODResource;
use res\liblod\LODLiteral;
use res\liblod\LODStatement;

use PHPUnit\Framework\TestCase;

final class LODInstanceTest extends TestCase
{
    private $testTriples;
    private $testUri = 'http://foo.bar/';

    function setUp()
    {
        $this->testTriples = array(
            new LODStatement($this->testUri, 'foaf:page', new LODResource('http://foo.bar/page1')),
            new LODStatement($this->testUri, 'foaf:page', new LODResource('http://foo.bar/page2')),
            new LODStatement($this->testUri, 'rdfs:seeAlso', new LODResource('http://foo.bar/page3')),
            new LODStatement($this->testUri, 'rdfs:label', new LODLiteral('Yoinch Chettner', array('lang' => 'en-gb'))),
            new LODStatement($this->testUri, 'rdf:type', new LODResource('http://purl.org/dc/dcmitype/StillImage')),
            new LODStatement($this->testUri, 'rdf:type', new LODResource('http://purl.org/ontology/po/TVContent'))
        );
    }

    function testMerge()
    {
        $instance = new LODInstance(new LOD(), $this->testUri);
        $instance->merge($this->testTriples);
        $this->assertEquals(6, count($instance->model));
    }

    function testFilter()
    {
        $instance = new LODInstance(new LOD(), $this->testUri, $this->testTriples);


        // basic filter
        $filteredInstance = $instance->filter('foaf:page');
        $this->assertEquals(2, count($filteredInstance->model));

        $expanded = Rdf::expandPrefix('foaf:page', Rdf::COMMON_PREFIXES);
        foreach($filteredInstance->model as $triple)
        {
            $this->assertEquals($expanded, $triple->predicate->value);
        }
    }

    function testIteration()
    {
        $instance = new LODInstance(new LOD(), $this->testUri, $this->testTriples);

        $expectedValues = array(
            'http://foo.bar/page1',
            'http://foo.bar/page2',
            'http://foo.bar/page3',
            'Yoinch Chettner',
            'http://purl.org/dc/dcmitype/StillImage',
            'http://purl.org/ontology/po/TVContent'
        );

        $actualValues = array();
        foreach($instance as $triple)
        {
            $actualValues[] = $triple->object->value;
        }

        $this->assertEquals($expectedValues, $actualValues);
    }

    function testArrayAccess()
    {
        $instance = new LODInstance(new LOD(), $this->testUri, $this->testTriples);

        // single predicate via offsetGet
        $filteredInstance1 = $instance['foaf:page'];
        $this->assertEquals(2, count($filteredInstance1->model));
        $expanded = Rdf::expandPrefix('foaf:page', Rdf::COMMON_PREFIXES);
        foreach($filteredInstance1->model as $triple)
        {
            $this->assertEquals($expanded, $triple->predicate->value);
        }

        // multiple predicates via offsetGet
        $filteredInstance2 = $instance['foaf:page,rdfs:seeAlso'];
        $this->assertEquals(3, count($filteredInstance2->model));

        // key existence via offsetExists
        $this->assertEquals(FALSE, isset($instance['fo:po']));
        $this->assertEquals(TRUE, isset($instance['foaf:page']));
    }

    function testHasType()
    {
        $instance = new LODInstance(new LOD(), $this->testUri, $this->testTriples);

        $this->assertEquals(TRUE, $instance->hasType('http://purl.org/dc/dcmitype/StillImage'));

        // matching one type out of several
        $this->assertEquals(TRUE, $instance->hasType('http://foo.bar/thing', 'http://purl.org/dc/dcmitype/StillImage'));
        $this->assertEquals(TRUE, $instance->hasType('http://foo.bar/thing', 'http://smoo.bar/foo', 'http://purl.org/ontology/po/TVContent'));

        // not matching any
        $this->assertEquals(FALSE, $instance->hasType('http://foo.bar/thing'));

        // matching on short form
        $this->assertEquals(TRUE, $instance->hasType('dcmitype:StillImage'));
        $this->assertEquals(TRUE, $instance->hasType('http://foo.bar/thing', 'http://smoo.bar/foo', 'po:TVContent'));
    }
}
?>
