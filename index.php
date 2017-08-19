<?php

  //$app = new App('http://localhost:8000/iiif/books/nyu_aco000398%252Fnyu_aco000398_afr03_d/full/500,/0/default.jpg');  

function main () {
  try {
    spl_autoload_register(function ($class) {
      require_once __DIR__ . '/include/class.' . strtolower($class) . '.php';
    });
    $app = new App();
    if ($app) {
      $app->output();
    }
  }
  catch (Exception $e) {
    // Bad Request: Services requested is not available
    http_response_code(400);
    print $e->getMessage();
  }
}

main();
