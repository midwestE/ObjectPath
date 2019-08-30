<?php

use MidwestE\ObjectPath;
use PHPUnit\Framework\TestCase;

class ObjectPathTest extends TestCase {

  private function objectPath() {
    $json = file_get_contents(__DIR__ . '/objectpath.json');
    return new ObjectPath($json);
  }

  function testLoading() {
    $this->assertNotEmpty($this->objectPath()->{'schema'});
  }

  function testDelimiter() {
    $o = $this->objectPath();
    $o->setDelimiter('/');
    $this->assertEquals('/', $o->getDelimiter());
  }

  function testToJson() {
    $o = $this->objectPath();
    $json = $o->toJson();
    $this->assertJson($json);
  }

  function testReset() {
    $o = $this->objectPath();
    $original = $o->toJson();
    $o->{'schema.properties.language.enum.0'} = 'Test';
    $after = $o->toJson();
    $this->assertNotEquals($original, $after);
    $o->reset();
    $this->assertEquals($original, $o->toJson());
  }

  function testFrom() {
    $o = $this->objectPath();
    $o->from('schema.properties.language');
    $this->assertEquals('schema.properties.language', $o->getFrom());
  }

  function testCopy() {
    $o = $this->objectPath();
    $o->from('schema.properties.language');
    $o->copy('enum', 'enumOriginal');
    $this->assertEquals($o->{'enum'}, $o->{'enumOriginal'});
  }

  function testSetArrayValue() {
    $o = $this->objectPath();
    $o->from('schema.properties.language');
    $o->set('enum.{English}', "ENG");
    $this->assertEquals($o->{'enum.{English}'}, 'ENG');
  }

  function testSetArrayByIndex() {
    $o = $this->objectPath();
    $o->from('schema.properties.language');
    $o->set('enum.0', 'Si');
    $this->assertEquals($o->{'enum.{0}'}, 'Si');
  }

  function testAdvancedUsage() {
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

}
