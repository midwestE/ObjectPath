<?php

use MidwestE\ObjectPath;
use PHPUnit\Framework\TestCase;

class ObjectPathTest extends TestCase
{

    private function jsonData()
    {
        return file_get_contents(__DIR__ . '/objectpath.json');
    }

    private function objectPath()
    {
        return new ObjectPath($this->jsonData());
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

    public function testSet()
    {
        $o = $this->objectPath();

        $object = new \stdClass;
        $object->array = [
            1 => 'int',
            '2' => 'string'
        ];

        $o->set('enum', $object);
        $enum = $o->get('enum');
        $this->assertSame($enum, $object);

        $o->{'form'} = $object;
        $form = $o->{'form'};
        $this->assertSame($form, $enum);
    }

    public function testMagicGetSet()
    {
        $o = $this->objectPath();

        $object = new \stdClass;
        $object->array = [
            1 => 'int',
            '2' => 'string'
        ];

        $o->set('enum', $object);
        $enum = $o->get('enum');
        $o->{'form'} = $object;
        $form = $o->{'form'};
        $this->assertSame($enum, $form);
    }

    public function testSetMustExist()
    {
        $o = $this->objectPath();

        $this->expectException(\Throwable::class);
        $o->set('fakekey', 'value', true);
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

    public function testRootSymbol()
    {
        $o = $this->objectPath();
        // test with and without symbol match
        $wSymbol = $o->{'$.form'};
        $woSymbol = $o->{'form'};
        $this->assertSame($wSymbol, $woSymbol);
        // change symbol and test against previous results
        $o->setRootSymbol('#');
        $newRootSymbol = $o->{'#.form'};
        $this->assertSame($wSymbol, $newRootSymbol);
        $this->assertSame($woSymbol, $newRootSymbol);
        // change symbol and delimiter maintain equality
        $o->setDelimiter('/');
        $newRootAndDelmiter = $o->{'#/form'};
        $this->assertSame($wSymbol, $newRootAndDelmiter);
        $this->assertSame($woSymbol, $newRootAndDelmiter);
    }

    public function testCache()
    {
        $o = $this->objectPath();
        $form = $o->{'form'};
        $this->assertTrue($o->isCached('form'));
        $schema = $o->{'schema'};
        $this->assertTrue($o->isCached('schema'));
        $this->assertTrue($o->isCached('form'));

        $o->setData($this->jsonData());
        $this->assertFalse($o->isCached('form'));

        // test cache is removed on unset
        $properties = $o->{'schema.properties'};
        $this->assertTrue($o->isCached('schema.properties'));
        $o->unset('schema.properties');
        $this->assertFalse($o->isCached('schema.properties'));
    }
}
