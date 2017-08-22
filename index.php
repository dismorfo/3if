<?php

function main () {
  try {
    require_once __DIR__ . '/include/class.app.php';
    $app = new App();
    //$app = new App('books/nyu_aco000398%252Fnyu_aco000398_afr03_d/full/500,/0/default.jpg');
    if ($app) {
      $app->output();
    }
    print $app->get('url');
    
  }
  catch (Exception $e) {
    http_response_code(400);
    print $e->getMessage();
  }
}

main();
