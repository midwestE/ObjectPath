<?php

use midwestE\ObjectPath;

class ObjectPathTest extends TestCase {

  function todo() {
    $json = file_get_contents('objectpath.json');
    $opath = new ObjectPath($json);
    $original = $opath->toJson();
    $opath->reset();
    $opath = $opath
      ->setDelimiter('/')
      ->from('schema/properties/language')
      ->copy('enum', 'enumOriginal')
      ->set('enum/{English}', "ENG")
      ->set('enum/0', 'Si')
      ->set('enum/{Spanish}', 'SPN')
      ->set('path', $opath->path());
    $opath->unset('enum/{Si}');
    $opath->unset('type');
  }

}
