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

use res\liblod\LOD;
use res\liblod\LODInstance;
use res\liblod\Rdf;
use res\liblod\LODResource;
use res\liblod\LODLiteral;
use res\liblod\LODStatement;

use PHPUnit\Framework\TestCase;

final class LODInstanceTest extends TestCase
{
    private $rdf;
    private $testTriples;
    private $testUri = 'http://foo.bar/';

    function setUp()
    {
        $this->rdf = new Rdf();

        $this->testTriples = array(
            new LODStatement($this->testUri, 'foaf:page', new LODResource('http://foo.bar/page1')),
            new LODStatement($this->testUri, 'foaf:page', new LODResource('http://foo.bar/page2')),
            new LODStatement($this->testUri, 'rdfs:seeAlso', new LODResource('http://foo.bar/page3')),
            new LODStatement($this->testUri, 'rdf:type', new LODResource('http://purl.org/dc/dcmitype/StillImage')),
            new LODStatement($this->testUri, 'rdf:type', new LODResource('http://purl.org/ontology/po/TVContent')),
            new LODStatement($this->testUri, 'schema:name', new LODLiteral('Y. Chettner', array('lang' => 'en-gb'))),
            new LODStatement($this->testUri, 'rdfs:label', new LODLiteral('Yoinch Chettner', array('lang' => 'en-gb'))),
            new LODStatement($this->testUri, 'dcterms:title', new LODLiteral('Monsieur Chettner', array('lang' => 'fr-fr'))),
            new LODStatement($this->testUri, 'dcterms:title', new LODLiteral('Herr Chettner', array('lang' => 'de-de')))
        );
    }

    function testMerge()
    {
        $instance = new LODInstance(new LOD(), $this->testUri);
        $instance->merge($this->testTriples);
        $this->assertEquals(9, count($instance->model));
    }

    function testFilter()
    {
        $instance = new LODInstance(new LOD(), $this->testUri, $this->testTriples);


        // basic filter
        $filteredInstance = $instance->filter('foaf:page');
        $this->assertEquals(2, count($filteredInstance->model));

        $expanded = $this->rdf->expandPrefix('foaf:page', Rdf::COMMON_PREFIXES);
        foreach($filteredInstance->model as $triple)
        {
            $this->assertEquals($expanded, $triple->predicate->value);
        }
    }

    function testIteration()
    {
        $instance = new LODInstance(new LOD(), $this->testUri, $this->testTriples);

        $this->assertEquals(0, $instance->key());

        $expectedValues = array(
            'http://foo.bar/page1',
            'http://foo.bar/page2',
            'http://foo.bar/page3',
            'http://purl.org/dc/dcmitype/StillImage',
            'http://purl.org/ontology/po/TVContent',
            'Y. Chettner',
            'Yoinch Chettner',
            'Monsieur Chettner',
            'Herr Chettner'
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
        $expanded = $this->rdf->expandPrefix('foaf:page', Rdf::COMMON_PREFIXES);
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

    // if multiple matching predicates are in the RDF, the statement
    // which matches the first preferred language should be returned
    function testLanguagePreference()
    {
        $lod = new LOD();
        $lod->languages = array('fr-fr', 'en-gb');

        $instance = new LODInstance($lod, $this->testUri, $this->testTriples);

        $object = $instance['rdfs:label,schema:name,dcterms:title'];
        $this->assertEquals('Monsieur Chettner', "$object");
    }

    // if languages is set to 'de-de', we should only get literals
    // which match that language, even if there are multiple statements
    // matching the predicate we specify
    function testFilterWithLanguages()
    {
        $lod = new LOD();
        $lod->languages = array('de-de');

        $instance = new LODInstance($lod, $this->testUri, $this->testTriples);

        $object = $instance->filter('dcterms:title');
        $this->assertEquals('Herr Chettner', "$object");
    }

    function testGetUri()
    {
        $instance = new LODInstance(new LOD(), $this->testUri, $this->testTriples);
        $this->assertEquals($this->testUri, $instance->__get('uri'));
    }

    function testToString()
    {
        $instance = new LODInstance(new LOD(), $this->testUri, $this->testTriples);
        $this->assertEquals($this->testUri, $instance->__toString());

        // an instance created via a filter which returns no statements
        // has '' as its string representation
        $filtered = $instance['boogle:woogle'];
        $this->assertEquals('', $filtered->__toString());
    }

    function testPropertySettersAndGetters()
    {
        $instance = new LODInstance(new LOD(), $this->testUri, $this->testTriples);

        // getting a non-existent property returns NULL
        $this->assertEquals(NULL, $instance->boo);

        // arbitrary properties can be set/unset
        $instance->foo = 'bar';
        $this->assertEquals('bar', $instance->foo);

        unset($instance->foo);
        $this->assertEquals(FALSE, isset($instance->foo));
    }

    function testSetModel()
    {
        $instance = new LODInstance(new LOD(), $this->testUri, $this->testTriples);
        $this->expectException(PHPUnit_Framework_Error_Warning::class);
        $instance->model = array();
    }

    function testSetUri()
    {
        $instance = new LODInstance(new LOD(), $this->testUri, $this->testTriples);
        $this->expectException(PHPUnit_Framework_Error_Warning::class);
        $instance->uri = 'http://boo.bar/';
    }

    function testSetExists()
    {
        $instance = new LODInstance(new LOD(), $this->testUri, $this->testTriples);
        $this->expectException(PHPUnit_Framework_Error_Warning::class);
        $instance->exists = TRUE;
    }
}
?>
