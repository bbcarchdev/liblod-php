<?php
require_once(dirname(__FILE__) . '/../src/rdf.php');

use PHPUnit\Framework\TestCase;

final class RdfTest extends TestCase
{
    public function testGetLiteralLanguage()
    {
        $str = '"Judi Dench"@en-gb';
        $expected = 'en-gb';
        $actual = Rdf::getLiteralLanguageAndDatatype($str)['lang'];
        $this->assertEquals($expected, $actual);
    }

    public function testGetLiteralDatatype()
    {
        $str = '"Judi Dench"^^<http://foo.bar/mytype>';
        $expected = 'http://foo.bar/mytype';
        $actual = Rdf::getLiteralLanguageAndDatatype($str)['datatype'];
        $this->assertEquals($expected, $actual);
    }

    public function testGetLiteralValue()
    {
        $expected = 'Judi Dench';

        $str = '"Judi Dench"^^<http://foo.bar/mytype>';
        $actual = Rdf::getLiteralValue($str);
        $this->assertEquals($expected, $actual);

        $str = '"Judi Dench"@en-gb';
        $actual = Rdf::getLiteralValue($str);
        $this->assertEquals($expected, $actual);
    }
}
