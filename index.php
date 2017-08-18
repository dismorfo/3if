<?php

define('DEBUG_MODE', TRUE);

/**
 * Provides central static variable storage.
 * See https://api.drupal.org/api/drupal/includes%21bootstrap.inc/function/drupal_static/7.x
 */
function &_static($name, $default_value = NULL, $reset = FALSE) {
  static $data = array(), $default = array();
  // First check if dealing with a previously defined static variable.
  if (isset($data[$name]) || array_key_exists($name, $data)) {
    // Non-NULL $name and both $data[$name] and $default[$name] statics exist.
    if ($reset) {
      // Reset pre-existing static variable to its default value.
      $data[$name] = $default[$name];
    }
    return $data[$name];
  }
  // Neither $data[$name] nor $default[$name] static variables exist.
  if (isset($name)) {
    if ($reset) {
      // Reset was called before a default is set and yet a variable must be
      // returned.
      return $data;
    }
    // First call with new non-NULL $name. Initialize a new static variable.
    $default[$name] = $data[$name] = $default_value;
    return $data[$name];
  }
  // Reset all: ($name == NULL). This needs to be done one at a time so that
  // references returned by earlier invocations of drupal_static() also get
  // reset.
  foreach ($default as $name => $value) {
    $data[$name] = $value;
  }
  // As the function returns a reference, the return should always be a
  // variable.
  return $data;
}

/**
 * Encodes special characters in a plain-text string for display as HTML.
 * Also validates strings as UTF-8 to prevent cross site scripting attacks on Internet Explorer 6.
 * See https://api.drupal.org/api/drupal/includes%21bootstrap.inc/function/check_plain/7.x
 */
function check_plain($text) {
  return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Formats a string for HTML display by replacing variable placeholders.
 * See https://api.drupal.org/api/drupal/includes%21bootstrap.inc/function/format_string/7.x
 */
function format_string($string, array $args = array()) {
  // Transform arguments before inserting them.
  foreach ($args as $key => $value) {
    // Escaped only.
    $args[$key] = check_plain($value);
  }
  return strtr($string, $args);
}

function base_path() {
  $base_path = &_static(__FUNCTION__);
  if (!isset($base_path)) {
    global $base_url, $base_path, $base_root;  
    $is_https = FALSE;  
    // Create base URL.
    $http_protocol = $is_https ? 'https' : 'http';   
    $base_root = $http_protocol . '://' . $_SERVER['HTTP_HOST'];  
    $base_url = $base_root;  
    // $_SERVER['SCRIPT_NAME'] can, in contrast to $_SERVER['PHP_SELF'], not
    // be modified by a visitor.
    if ($dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '\/')) {
      $base_path = $dir;
      $base_url .= $base_path;
      $base_path .= '/';
    }
    else {
      $base_path = '/';
    }
  }
  return $base_path;
}

function arguments() {
  $arguments = &_static(__FUNCTION__);
  if (!isset($arguments)) {
    $base_path = base_path();
    $input = rtrim($_SERVER['REQUEST_URI'], $base_path);
    $uri = filter_var($input, FILTER_SANITIZE_STRING);
    $parse_url = parse_url($uri);
    $path = $parse_url['path'];
    $arguments = explode('/', $path);
    unset($arguments[0]);
    $count = count($arguments);
    // don't waste time.
    if ($count < 2 || $count > 6 ) {
      http_response_code(400); // Bad Request
      die(format_string('Bad Request @request', array('@request' => $input)));
    }
  }
  return $arguments;
}

function _http_build_query(array $query, $parent = '') {
  $params = array();
  foreach ($query as $key => $value) {
    $key = $parent ? $parent . rawurlencode('[' . $key . ']') : rawurlencode($key);
    // Recurse into children.
    if (is_array($value)) {
      $params[] = _http_build_query($value, $key);
    }
    // If a query parameter value is NULL, only append its key.
    elseif (!isset($value)) {
      $params[] = $key;
    }
    else {
      // For better readability of paths in query strings, we decode slashes.
      $params[] = $key . '=' . str_replace('%2F', '/', rawurlencode($value));
    }
  }
  return implode('&', $params);
}

function strip_dangerous_protocols($uri) {
  static $allowed_protocols;
  if (!isset($allowed_protocols)) {
    $allowed_protocols = array_flip(
      array(
        'ftp', 
        'http', 
        'https', 
        'irc', 
        'mailto', 
        'news', 
        'nntp', 
        'rtsp', 
        'sftp', 
        'ssh', 
        'tel', 
        'telnet', 
        'webcal'
      )
    );
  }
  // Iteratively remove any invalid protocol found.
  do {
    $before = $uri;
    $colonpos = strpos($uri, ':');
    if ($colonpos > 0) {
      // We found a colon, possibly a protocol. Verify.
      $protocol = substr($uri, 0, $colonpos);
      // If a colon is preceded by a slash, question mark or hash, it cannot
      // possibly be part of the URL scheme. This must be a relative URL, which
      // inherits the (safe) protocol of the base document.
      if (preg_match('![/?#]!', $protocol)) {
        break;
      }
      // Check if this is a disallowed protocol. Per RFC2616, section 3.2.3
      // (URI Comparison) scheme comparison must be case-insensitive.
      if (!isset($allowed_protocols[strtolower($protocol)])) {
        $uri = substr($uri, $colonpos + 1);
      }
    }
  } while ($before != $uri);
  return $uri;
}

function url_is_external($path) {
  $colonpos = strpos($path, ':');
  // Some browsers treat \ as / so normalize to forward slashes.
  $path = str_replace('\\', '/', $path);
  // If the path starts with 2 slashes then it is always considered an external
  // URL without an explicit protocol part.
  return (strpos($path, '//') === 0)
    // Leading control characters may be ignored or mishandled by browsers, so
    // assume such a path may lead to an external location. The \p{C} character
    // class matches all UTF-8 control, unassigned, and private characters.
    || (preg_match('/^\p{C}/u', $path) !== 0)
    // Avoid calling drupal_strip_dangerous_protocols() if there is any slash
    // (/), hash (#) or question_mark (?) before the colon (:) occurrence - if
    // any - as this would clearly mean it is not a URL.
    || ($colonpos !== FALSE
    && !preg_match('![/?#]!', substr($path, 0, $colonpos))
    && strip_dangerous_protocols($path) == $path);
}

function url($path = NULL, array $options = array()) {

  // Merge in defaults.
  $options += array(
    'fragment' => '',
    'query' => array(),
    'absolute' => FALSE,
    'alias' => FALSE,
    'prefix' => ''
  );

  if (!isset($options['external'])) {
    $options['external'] = url_is_external($path);
  }

  // Preserve the original path before altering or aliasing.
  $original_path = $path;

  if (isset($options['fragment']) && $options['fragment'] !== '') {
    $options['fragment'] = '#' . $options['fragment'];
  }

  if ($options['external'] ) {
    // Split off the fragment.
    if (strpos($path, '#') !== FALSE) {
      list($path, $old_fragment) = explode('#', $path, 2);
      // If $options contains no fragment, take it over from the path.
      if (isset($old_fragment) && !$options['fragment']) {
        $options['fragment'] = '#' . $old_fragment;
      }
    }
    // Append the query.
    if ($options['query']) {
      $path .= (strpos($path, '?') !== FALSE ? '&' : '?') . _http_build_query($options['query']);
    }
    if (isset($options['https']) && variable_get('https', FALSE)) {
      if ($options['https'] === TRUE) {
        $path = str_replace('http://', 'https://', $path);
      }
      elseif ($options['https'] === FALSE) {
        $path = str_replace('https://', 'http://', $path);
      }
    }
    // Reassemble.
    return $path . $options['fragment'];
  }

  // Strip leading slashes from internal paths to prevent them becoming external
  // URLs without protocol. /example.com should not be turned into
  // //example.com.
  $path = ltrim($path, '/');

  global $base_url, $base_secure_url, $base_insecure_url;

  // The base_url might be rewritten from the language rewrite in domain mode.
  if (!isset($options['base_url'])) {
    if (isset($options['https']) && variable_get('https', FALSE)) {
      if ($options['https'] === TRUE) {
        $options['base_url'] = $base_secure_url;
        $options['absolute'] = TRUE;
      }
      elseif ($options['https'] === FALSE) {
        $options['base_url'] = $base_insecure_url;
        $options['absolute'] = TRUE;
      }
    }
    else {
      $options['base_url'] = $base_url;
    }
  }

  $path = $prefix . $path;
  
  $query = array();
  
  if (!empty($path)) {
    $query['q'] = $path;
  }
  
  if ($options['query']) {
    // We do not use array_merge() here to prevent overriding $path via query
    // parameters.
    $query += $options['query'];
  }
  
  $query = $query ? ('?' . _http_build_query($query)) : '';
  
  $script = isset($options['script']) ? $options['script'] : '';
  
  return $base . $script . $query . $options['fragment'];
  
}

function djatoka_url($identifier, $options = array()) {

  // Introducing djatoka
  // http://www.dlib.org/dlib/september08/chute/09chute.html
	
  // Djatoka URL
  $service = 'http://dl-img.home.nyu.edu/adore-djatoka';
	
  // Service to request a Region
  $svc_id = 'info:lanl-repo/svc/getRegion';

  // OpenURL
  // http://www.niso.org/apps/group_public/document.php?document_id=14831
  $url_ver = 'Z39.88-2004';
  
  // Metadata Format specifying parameters to request a Region
  $svc_val_fmt = 'info:ofi/fmt:kev:mtx:jpeg2000';
  
  // Mime type of the image format to be provided as response.
  $format = 'image/jpeg';
	
  // Integer. Where 0 is the lowest resolution with each increment doubling the image in
  // size. Default: Max level of requested image, based on the number of Discrete 
  // Wavelet Transform (DWT) decomposition levels.
  $level = 0;

  // Rotates image by 90/180/270 degrees clockwise.
  $rotate = 0;
  
  $files_server = 'http://dlib.nyu.edu/files';
	
  $identifier = $files_server . '/' . $identifier;
		
  $arguments = array(
	'url_ver' => $url_ver,
	'svc_id' => $svc_id,
	'svc_val_fmt' => $svc_val_fmt,
	'svc.format' => $format,
	'rft_id' => $identifier,
  );
	
  $files_server = 'http://dlib.nyu.edu/files';
  
  /**
   * svc.region
   * var path = OpenLayers.Layer.OpenURL.djatokaURL + "?url_ver=" + this.url_ver + "&rft_id=" + this.rft_id +
   * "&svc_id=" + this.svc_id + "&svc_val_fmt=" + this.svc_val_fmt + "&svc.format=" +
   * this.format + "&svc.level=" + z + "&svc.rotate=0&svc.region=" + this.tilePos.lat + "," +
   * this.tilePos.lon + "," + this.imageSize.h + "," + this.imageSize.w;
   */  
	
  //if (isset($file['image_style'])) {
  //  $dimmensions = explode("x", $file['image_style']);
  //  if (count($dimmensions) == 2 && is_numeric($dimmensions[1])) {
  //  	$arguments['svc.scale'] = $dimmensions[1];
  //  }
  //}
  
  if ($options['size']) {    
    // $arguments['svc.scale'] = '230';
    $size = explode(',', $options['size']);
    $width = $size[0];    
    $height = $size[1];
    if ($width && $height) {
      //krumo('resize by width and height');
    }
    else if ($width && !$height) {
      //krumo('resize by width');
    }
    else if (!$width && $height) {
      //$arguments['svc.scale'] = $height;
    }
  }  
  
  $arguments['svc.rotate'] = $options['rotation'];
  
  $url = url($service . '/resolver', array('external' => true, 'query' => $arguments));
  
  if (DEBUG_MODE) {
  
    header("Content-type: $format");
    
    list($source_image_width, $source_image_height, $source_image_type) = getimagesize($url);  
    
    $source_gd_image = imagecreatefromjpeg($url);
    
    $source_aspect_ratio = $source_image_width / $source_image_height;
    
    $thumbnail_gd_image = imagecreatetruecolor($width, $height);
    
    imagecopyresampled($thumbnail_gd_image, $source_gd_image, 0, 0, 0, 0, $width, $thumbnail, $source_image_width, $source_image_height);

    imagejpeg($source_gd_image);

    imagedestroy($source_gd_image);
    
  }
  else {
    // Moved Permanently
    // This and all future requests should be directed to the given URI.
    http_response_code(301);
    header("Location: $url");
  }  
}

function request_image($options) {
  // {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
  // e.g., http://www.example.org/image-service/abcd1234/full/full/0/default.jpg
  // the service (books|photos|maps|others)
  $prefix = $options['service'];
  // resource identifier
  $resource = $options['identifier'];
  // we **alway** assume JP2
  $extension = 'jp2';
  // image identifier
  $identifier = "$prefix/$resource.$extension";
  // request image
  return djatoka_url($identifier, $options);
}

function services() {
  return array(
    'books',
    'photos',
    'maps',
    'images',
  );
}

function service() {
  $arguments = arguments();
  // service should be the 1st argument in the query
  $service = $arguments[1];
  // get a list of available services
  $services = services();
  // services requested is not available
  if (!in_array($service, $services)) {
    http_response_code(400); // Bad Request
    die(format_string('Invalid service @service', array('@service' => $service)));
  }
  // argument 2 should be a valid services
  return $service;
}

function identifier() {
  $arguments = arguments();
  // 3rd argument should be the identifier
  $test = $arguments[2];
  // our content live inside directories, we need to pass / (forward slash) 
  // as part of the argument.
  // Apache return 404 if we urlencode / (forward slash) as 2F
  // We work around this issue by urlencode-ing 2 times the / (forward slash)
  // https://stackoverflow.com/questions/7544759/cannot-match-2f-in-mod-rewrite
  // http://httpd.apache.org/docs/2.2/mod/core.html#allowencodedslashes
  $find = '%252F'; // Same as urlencode(urlencode('/'))
  // check for slashes
  $pos = strpos($test, $find);
  if ($pos) {
    $identifier = urldecode(urldecode($test));
  }
  else {
    $identifier = $test;
  }
  // get the pathinfo to check for extension
  $pathinfo = pathinfo($identifier);
  // remove extension
  if (isset($pathinfo['extension'])) {
    // we should throw exception
    $identifier = str_replace('.' . $pathinfo['extension'], '', $identifier);
  }
  return $identifier;
}

/**
 * Region
 * http://iiif.io/api/image/2.1/#region
 * The region parameter defines the rectangular portion of the
 * full image to be returned. Region can be specified by pixel 
 * coordinates, percentage or by the value “full”, which 
 * specifies that the entire image should be returned.
 */
function regions() {
  // Looking forward to use Loris, in the meantime we only "implement" full
  return array(
    'full',
  );
}

/**
 * Size
 * http://iiif.io/api/image/2.1/#size
 * The size parameter determines the dimensions to which 
 * the extracted region is to be scaled.
 *
 * NOTE: If the resulting height or width is zero, then 
 * the server should return a 400 (bad request) status code.
 */
function size() {
  $arguments = arguments();
  if (isset($arguments[4])) {
    $input = $arguments[4];
    switch ($input) {
      /**
       * Deprecation Warning The size keyword full will be replaced in
       * favor of max in version 3.0. Until that time, the w, syntax 
       * should be considered the canonical form of request for the max
       * size, unless max is equivalent to full.    
       */
      case 'full':
      /**
       * The image or region is returned at the maximum size available, 
       * as indicated by maxWidth, maxHeight, maxArea in the profile 
       * description. This is the same as full if none of these 
       * properties are provided.   
       */      
      case 'max':
        return FALSE; // if no size, Djatoka Image Server will return the original size
        break;
      /**
       * Test w, OR ,h ORO w,h
       */
      default:
        
        $pattern = '/^(\d*),{1}$|^,{1}(\d*)$|^(\d*),{1}(\d*)$/';
        
        preg_match($pattern, $input, $matches);
        
        /**
         * The image or region should be scaled so that its 
         * width is exactly equal to w, and the height will be
         * a calculated value that maintains the aspect ratio 
         * of the extracted region.
         */       
         //'w,',
         /**
          * The image or region should be scaled so that 
          * its height is exactly equal to h, and the width 
          * will be a calculated value that maintains the 
          * aspect ratio of the extracted region.    
          */
          //',h',   
        if ($matches) {
          return $matches[0];
        }
        else {
          http_response_code(400); // Bad Request          
          die('Please provide valid size.');          
        }
    }
  }
  else {
    http_response_code(400); // Bad Request
    die('Please provide valid size.');
  }
}

/**
 * Quality
 * See http://iiif.io/api/image/2.1/#quality
 * The quality parameter determines whether the image is 
 * delivered in color, grayscale or black and white.
 */
function qualities() {
  return array(
    'color', // The image is returned in full color. 
    'gray', // The image is returned in grayscale, where each pixel is black, white or any shade of gray in between.
    'bitonal', // The image returned is bitonal, where each pixel is either black or white.
    'default', // The image is returned using the server’s default quality (e.g. color, gray or bitonal) for the image.
  );
}

function quality() {
  $arguments = arguments();
  if (isset($arguments[6])) {
    // service should be the 1st argument in the query
    $input = $arguments[6];
    $pathinfo = pathinfo($input);
    if (isset($pathinfo['extension'])) {
      $qualities = qualities();
      $quality = $pathinfo['filename'];
      if (in_array($quality, $qualities)) {
        return $pathinfo['basename'];
      }
      else {
        http_response_code(400); // Bad Request
        die('Please provide valid quality.');        
      }
    }
    else {
      http_response_code(400); // Bad Request
      die('Please provide valid quality.');
    }
  }
}

function rotation() {
  // rotates image by 90/180/270 degrees clockwise.
  $arguments = arguments();  
  if (isset($arguments[5])) {
    $input = $arguments[5];
    if (is_numeric($input)) {
      $rotation = (int) $input;
      if ($rotation < 90) $rotation = 0;
      if ($rotation >= 90 && $rotation < 180) $rotation = 90;
      if ($rotation >= 180 && $rotation < 270) $rotation = 180;
      if ($rotation >= 270) $rotation = 270;      
      return $rotation;
    }
    else {
      http_response_code(400); // Bad Request
      die(format_string('Invalid rotation @rotation', array('@rotation' => $input)));
    }
  }
  else {
    http_response_code(400); // Bad Request
    die('Please provide rotation');
  }
}

function region() {
  $arguments = arguments();
  if (isset($arguments[3])) {
    $regions = regions();
    // 3rd argument should be the region
    $region = $arguments[3];
    // region not available
    if (!in_array($region, $regions)) {
      die(format_string('Invalid region @region', array('@region' => $region)));
    }
    return $region;
  }
  else {
    die('Please provide region.');
  }
}

function main() {
  // http://iiif.localhost:8000/books/nyu_aco000398%252Fnyu_aco000398_afr01_d/full/full/default.jpg
  if (DEBUG_MODE) include_once './include/krumo/class.krumo.php';
  // {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
  // get service
  $service = service(); // prefix
  // get identifier
  $identifier = identifier();
  // get region
  $region = region();
  // get size
  $size = size();
  // get rotation
  $rotation = rotation();
  // get quality
  $quality = quality();  
  return request_image(
    array(
      'service' => $service, 
      'identifier' => $identifier,
      'region' => $region,
      'size' => $size,
      'rotation' => $rotation,
      'quality' => $quality,
    )
  );
}

main();

// http://httpd.apache.org/docs/2.2/mod/core.html#allowencodedslashes
// we have some strong assumptions of what are we doing here
// https://stackoverflow.com/questions/7544759/cannot-match-2f-in-mod-rewrite
// double urlencode the URL
//print urlencode(urlencode('nyu_aco000398/nyu_aco000398_afr01_d'));
