<?php

// http_response_code not in PHP 5.3.3
// http://php.net/manual/en/function.http-response-code.php
function status_header($setHeader = NULL) {
  static $theHeader = NULL;
  if ($theHeader) {
    return $theHeader;
  }
  $theHeader = $setHeader;
  header("HTTP/1.1 $setHeader");
  return $setHeader;
}

function main () {
  try {
    require_once __DIR__ . '/include/class.app.php';
    $app = new App();
    if ($app) {
      $app->output();
    }
  }
  catch (Exception $e) {
    status_header(400);
    print $e->getMessage();
  }
}

main();