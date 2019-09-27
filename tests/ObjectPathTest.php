<?php

use MidwestE\ObjectPath;
use PHPUnit\Framework\TestCase;

class ObjectPathTest extends TestCase
{

    private function objectPath()
    {
        $json = file_get_contents(__DIR__ . '/objectpath.json');
        return new ObjectPath($json);
    }

    public function testLoading()
    {
        $this->assertNotEmpty($this->objectPath()->{'schema'});
    }

    public function testDelimiter()
    {
        $o = $this->objectPath();
        $o->setDelimiter('/');
        $this->assertEquals('/', $o->getDelimiter());
    }

    public function testToJson()
    {
        $o = $this->objectPath();
        $json = $o->toJson();
        $this->assertJson($json);
    }

    public function testReset()
    {
        $o = $this->objectPath();
        $original = $o->toJson();
        $o->{'schema.properties.language.enum.0'} = 'Test';
        $after = $o->toJson();
        $this->assertNotEquals($original, $after);
        $o->reset();
        $this->assertEquals($original, $o->toJson());
    }

    public function testFrom()
    {
        $o = $this->objectPath();
        $o->from('schema.properties.language');
        $this->assertEquals('schema.properties.language', $o->getFrom());
    }

    public function testCopy()
    {
        $o = $this->objectPath();
        $o->from('schema.properties.language');
        $o->copy('enum', 'enumOriginal');
        $this->assertEquals($o->{'enum'}, $o->{'enumOriginal'});
    }

    public function testSetArrayValue()
    {
        $o = $this->objectPath();
        $o->from('schema.properties.language');
        $o->set('enum.{English}', "ENG");
        $this->assertEquals($o->{'enum.{English}'}, 'ENG');
    }

    public function testSetArrayByIndex()
    {
        $o = $this->objectPath();
        $o->from('schema.properties.language');
        $o->set('enum.0', 'Si');
        $this->assertEquals($o->{'enum.{0}'}, 'Si');
    }

    public function testAdvancedUsage()
    {
        $o = $this->objectPath();

        $o->setDelimiter('/');
        $this->assertEquals('/', $o->getDelimiter());

        $o->from('schema/properties/language');
        $this->assertEquals('schema/properties/language', $o->getFrom());

        $o->copy('enum', 'enumOriginal');
        $this->assertEquals($o->{'enum'}, $o->{'enumOriginal'});

        $o->set('enum/{English}', "ENG");
        $this->assertEquals($o->{'enum/{English}'}, 'ENG');

        $o->set('enum/0', 'Si');
        $this->assertEquals($o->{'enum/{0}'}, 'Si');
    }

    public function testMagicEmptyIsset()
    {
        $o = $this->objectPath();
        $enum = $o->{'schema.properties.language.enum'};
        $this->assertIsArray($enum);
        // empty tests
        $this->assertEquals(empty($enum), empty($o->{'schema.properties.language.enum'}));
        $this->assertFalse(empty($o->{'schema.properties.language.enum'}));
        $this->assertTrue(empty($o->{'schema.properties.language.fake'}));
        // isset tests
        $this->assertEquals(isset($enum), isset($o->{'schema.properties.language.enum'}));
        $this->assertTrue(isset($o->{'schema.properties.language.enum'}));
        $this->assertFalse(isset($o->{'schema.properties.language.fake'}));
    }

    public function testParent()
    {
        $o = $this->objectPath();
        // test parent return parent when no parents exist
        $child = $o->{'form'};
        $parent = $o->getParent('form');
        $this->assertSame($child, $parent);
        // test parent
        $child = $o->getParent('schema.properties.language.enum');
        $parent = $o->{'schema.properties.language'};
        $this->assertSame($child, $parent);
    }
}
