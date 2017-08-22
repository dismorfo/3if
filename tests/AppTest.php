<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers App
 */
final class AppTest extends TestCase {
  public function testWillAlterOutput() {
    
    require_once '../include/class.app.php';
    
    $request = 'books/nyu_aco000398%252Fnyu_aco000398_afr03_d/full/500,500/0/default.jpg';

    $app = new App($request);
    
    $alter = $app->get('alter');
    
    $this->assertTrue($alter);
    
  }
}