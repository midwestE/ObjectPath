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

        $o->set('enum', $object, false);
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

        $o->set('enum', $object, false);
        $enum = $o->get('enum');
        $o->{'form'} = $object;
        $form = $o->{'form'};
        $this->assertSame($enum, $form);
    }

    public function testCacheSubItems()
    {
        $o = $this->objectPath();

        $o->set('schema.title', 'New Title', true);
        $this->assertTrue($o->isCached('schema'));

        $schema = $o->{'schema'};
        $this->assertEquals($schema->title, 'New Title');

        $o->set('schema.title', 'Changed Title', true);
        $schema = $o->{'schema'};
        $this->assertEquals($schema->title, 'Changed Title');
    }

    public function testSetMustExist()
    {
        $o = $this->objectPath();
        $o->set('schema.title', 'New Title', true);

        $this->expectException(\Throwable::class);
        $o->set('fakekey', 'value', true);

        $this->expectException(\Throwable::class);
        $o->set('schema.this.that.otherthing', 'value', true);
    }

    public function testSetNonExisting()
    {
        $o = $this->objectPath();
        $o->set('schema.another.key', 'New Title', false);
        $this->assertEquals($o->{'schema.another.key'}, 'New Title');
        $o->{'schema.another.key'} = 'New Title 2';
        $this->assertEquals($o->{'schema.another.key'}, 'New Title 2');

        $o = $this->objectPath();
        $o->set('schema.properties.language.enum.newArray', ['this', 'that'], false);
        $this->assertEquals($o->{'schema.properties.language.enum.newArray'}, ['this', 'that']);
        $o->{'schema.properties.language.enum.newArray'} = ['that', 'otherThing'];
        $this->assertEquals($o->{'schema.properties.language.enum.newArray'}, ['that', 'otherThing']);

        $o = $this->objectPath();
        $o->set('schema.properties.language.enum.{English}', ['this', 'that'], false);
        $this->assertEquals($o->{'schema.properties.language.enum.0'}, ['this', 'that']);
        $o->{'schema.properties.language.enum.0'} = ['that', 'otherThing'];
        $this->assertEquals($o->{'schema.properties.language.enum.0'}, ['that', 'otherThing']);
    }

    public function testSetArrayValue()
    {
        $o = $this->objectPath();
        $o->from('schema.properties.language');
        $o->set('enum.{English}', "ENG");
        $this->assertEquals($o->{'enum.0'}, 'ENG');
        $this->assertEquals($o->{'enum.{ENG}'}, 'ENG');
        $this->assertFalse($o->exists('enum.{English}'));

        // test array by value set is removed from cache and no longer exists
        $o->set('enum.{ENG}', "Spanish");
        $this->assertFalse($o->exists('enum.{ENG}'));
        $this->assertFalse($o->isCached('enum.{ENG}'));
        $this->assertEmpty($o->{'enum.{ENG}'});
    }

    public function testSetArrayByIndex()
    {
        $o = $this->objectPath();
        $o->from('schema.properties.language');
        $o->set('enum.0', 'Si');
        $this->assertEquals($o->{'enum.0'}, 'Si');
        $this->assertEquals($o->{'enum.{Si}'}, 'Si');
        $this->assertFalse($o->exists('enum.{English}'));
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
        $this->assertEquals($o->{'enum/{ENG}'}, 'ENG');

        $o->set('enum/0', 'Si');
        $this->assertEquals($o->{'enum/0'}, 'Si');
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
        $child = $o->{'$'};
        $parent = $o->parent('$');
        $this->assertSame($child, $parent);
        // test parent return parent when no parents exist
        $child = $o->{'.'};
        $parent = $o->parent('$');
        $this->assertSame($child, $parent);
        // test parent return parent when no parents exist
        $child = $o->{''};
        $parent = $o->parent('$');
        $this->assertSame($child, $parent);
        // test parent
        $child = $o->parent('schema.properties.language.enum');
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

    public function testUnset()
    {
        $o = $this->objectPath();
        $o->unset('schema.properties.language.enum');
        $this->assertFalse($o->exists('schema.properties.language.enum'));
        $o->unset('schema.properties.language');
        $this->assertFalse($o->exists('schema.properties.language'));
        $o->unset('schema.properties');
        $this->assertFalse($o->exists('schema.properties'));
        $o->unset('schema');
        $this->assertFalse($o->exists('schema'));
        $o->unset('$');
        $this->assertFalse($o->exists('$'));
    }

    public function testReferenceUnset()
    {
        $o = $this->objectPath();

        // cache items
        $null1 = $o->{'schema.value1'};
        $null2 = $o->{'schema.value2'};
        $this->assertSame($null1, $null2);

        // set items and make sure null reference is detached
        $o->set('schema.value1', 'value1', false);
        $o->set('schema.value2', 'value2', false);
        $this->assertNotSame($o->{'schema.value1'}, $o->{'schema.value2'});

        // set value1 to value2
        $o->{'schema.value1'} = $o->{'schema.value2'};
        $this->assertEquals('value2', $o->{'schema.value1'});

        // set new value2 and make sure value1 is unchanged
        $o->{'schema.value2'} = 'new2';
        $this->assertEquals('value2', $o->{'schema.value1'});

        // copy
        $o->copy('schema.value2', 'schema.value1');
        $this->assertSame($o->{'schema.value1'}, $o->{'schema.value2'});
        $o->{'schema.value2'} = 'aftercopy';
        $this->assertEquals('new2', $o->{'schema.value1'});

        // set to object
        $o->{'schema.value2'} = new \stdClass();
        $this->assertIsNotObject($o->{'schema.value1'});

        // set to array
        $o->{'schema.value2'} = [];
        $o->{'schema.value2'}['key'] = 'value';
        $this->assertIsNotArray($o->{'schema.value1'});
    }
}
