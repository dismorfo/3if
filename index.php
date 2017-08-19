<?php

class App {
  
  // Djatoka URL
  private $resolver = 'http://dl-img.home.nyu.edu/adore-djatoka/resolver';

  private $files_server = 'http://dlib.nyu.edu/files';

  public function __construct() {
    
    //include_once './include/krumo/class.krumo.php';
    
    include_once 'include/class.image.php';

    $url = $this->get('url');

    $size = $this->get('size');

    if ($size) {
      $size = explode(',', $size);
      $width = $size[0];
      $height = $size[1];
      if ($width && $height) {
        $image = new Image();
        $image->load($url);
        $image->resize($width, $height);
        $image->output($image);
        exit;
      }
      else if ($width && !$height) {
        $image = new Image();
        $image->load($url);
        $image->resizeToWidth($width);
        $image->output();
        exit;
      }
      else {
        // Use Djakota to scale using height
        // This and all future requests should be directed to the given URI.
        http_response_code(301);
        header("Location: $url");
      }      
    }
    else {
      // Use Djakota to scale using height
      // This and all future requests should be directed to the given URI.
      http_response_code(301);
      header("Location: $url");
    }
  }

  public function get($name) {
    if (method_exists($this, ($method = 'get_' . $name))) {
      return $this->$method();
    }
    else return;
  }

  private function http_build_query(array $query, $parent = '') {
    $params = array();
    foreach ($query as $key => $value) {
      $key = $parent ? $parent . rawurlencode('[' . $key . ']') : rawurlencode($key);
      // Recurse into children.
      if (is_array($value)) {
        $params[] = http_build_query($value, $key);
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

  /**
   * Provides central static variable storage.
   * See https://api.drupal.org/api/drupal/includes%21bootstrap.inc/function/drupal_static/7.x
   */
   private function &_static($name, $default_value = NULL, $reset = FALSE) {
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

  public function get_arguments() {
    $arguments = &$this->_static(__FUNCTION__);
    if (!isset($arguments)) {
      $base_path = $this->base_path();    
      $uri = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_STRING);  
      // remove $base_path from uri
      if (0 === strpos($uri, $base_path)) {
        $uri = substr($uri, strlen($base_path));
      }
      $arguments = explode('/', $uri);
      if (empty($arguments[0])) {
        // remove empty value
        unset($arguments[0]);
        // reset index
        array_merge($arguments);
      }  
      $count = count($arguments);
      // don't waste time.
      if ($count < 2 || $count > 6 ) {
        http_response_code(400); // Bad Request
        die(format_string('Bad Request @request', array('@request' => $input)));
      }
    }
    return $arguments;    
  }

  /**
   * Formats a string for HTML display by replacing variable placeholders.
   * See https://api.drupal.org/api/drupal/includes%21bootstrap.inc/function/format_string/7.x
   */
  public function format_string($string, array $args = array()) {
    // Transform arguments before inserting them.
    foreach ($args as $key => $value) {
      // Escaped only.
      $args[$key] = check_plain($value);
    }
    return strtr($string, $args);
  }

  /**
   * Encodes special characters in a plain-text string for display as HTML.
   * Also validates strings as UTF-8 to prevent cross site scripting attacks on Internet Explorer 6.
   * See https://api.drupal.org/api/drupal/includes%21bootstrap.inc/function/check_plain/7.x
   */
  function check_plain($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
  }

  private function base_path() {
    $base_path = &$this->_static(__FUNCTION__);
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

  private function build_url($path = NULL, array $options = array()) {
    // Merge in defaults.
    $options += array(
      'query' => array(),
    );
    // Append the query.
    if ($options['query']) {
      $path .= (strpos($path, '?') !== FALSE ? '&' : '?') . http_build_query($options['query']);
    }
    return $path;
  }  

  private function get_service() {
    
    $arguments = $this->get('arguments');
    // {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
    // service should be the 1st argument in the query
    $service = $arguments[0];
      
    // get a list of available services
    $services = $this->get('services');
      
    if (in_array($service, $services)) return $service;
    
    // Bad Request: Services requested is not available
    http_response_code(400);
      
    // Die fast
    die(format_string('Invalid service @service', array('@service' => $service)));
  }

  private function get_identifier() {
    $arguments = $this->get('arguments');
    // {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
    $test = $arguments[1];
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
   * The region parameter defines the rectangular portion of the
   * full image to be returned. Region can be specified by pixel 
   * coordinates, percentage or by the value “full”, which 
   * specifies that the entire image should be returned.
   * See http://iiif.io/api/image/2.1/#region
   */
  private function get_region() {
    $arguments = $this->get('arguments');
    // {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
    if (isset($arguments[2])) {
      $regions = $this->get('regions');
      // 2nd argument should be the region
      $region = $arguments[2];
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

  /**
   * The size parameter determines the dimensions to which 
   * the extracted region is to be scaled.   
   * See http://iiif.io/api/image/2.1/#size
   */
   private function get_size() {
     $arguments = $this->get('arguments');
     // {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
     if (isset($arguments[3])) {
       $input = $arguments[3];
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

  private function get_rotation() {
    // rotates image by 90/180/270 degrees clockwise.
    $arguments = $this->get('arguments');  
    // {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
    if (isset($arguments[4])) {
      $input = $arguments[4];
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

  /**
   * The quality parameter determines whether the image is 
   * delivered in color, grayscale or black and white.
   * See http://iiif.io/api/image/2.1/#quality
   */
  private function get_quality() {
    $arguments = $this->get('arguments');
    // {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
    if (isset($arguments[5])) {
      // service should be the 1st argument in the query
      $input = $arguments[5];
      $pathinfo = pathinfo($input);
      if (isset($pathinfo['extension'])) {
        $qualities = $this->get('qualities');
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

  private function get_services() {
    return array(
      'books',
      'photos',
      'maps',
      'images',
    );
  }
  
  private function get_regions() {
    // Looking forward to use Loris, in the meantime we only "implement" full
    return array(
      'full',
    );
  }
  
  private function get_qualities() {
    return array(
      'color', // The image is returned in full color. 
      'gray', // The image is returned in grayscale, where each pixel is black, white or any shade of gray in between.
      'bitonal', // The image returned is bitonal, where each pixel is either black or white.
      'default', // The image is returned using the server’s default quality (e.g. color, gray or bitonal) for the image.
    );
  }

  private function get_url() {

    // Introducing djatoka
    // http://www.dlib.org/dlib/september08/chute/09chute.html
    
    // {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
    // e.g., http://www.example.org/image-service/abcd1234/full/full/0/default.jpg
    // the service (books|photos|maps|others)
    $prefix = $this->get('service');
      
    // resource identifier
    $resource = $this->get('identifier');
      
    // we **alway** assume JP2
    $format = 'jp2';
      
    // image identifier
    $identifier = "$prefix/$resource.$format";  
        
    // Djatoka URL
    $service = $this->resolver;
    
    // Service to request a Region
    $svc_id = 'info:lanl-repo/svc/getRegion';
    
    // OpenURL
    // http://www.niso.org/apps/group_public/document.php?document_id=14831
    $url_ver = 'Z39.88-2004';
      
    // Metadata Format specifying parameters to request a Region
    $svc_val_fmt = 'info:ofi/fmt:kev:mtx:jpeg2000';
      
    // Mime type of the image format to be provided as response.
    $mime = 'image/jpeg';
      
    // Integer. Where 0 is the lowest resolution with each increment doubling the image in
    // size. Default: Max level of requested image, based on the number of Discrete 
    // Wavelet Transform (DWT) decomposition levels.
    $level = 0;
    
    // Rotates image by 90/180/270 degrees clockwise.
    $rotate = 0;
    
    $files_server = $this->files_server;

    $identifier = $files_server . '/' . $identifier;
    
    $arguments = array(
      'url_ver' => $url_ver,
      'svc_id' => $svc_id,
      'svc_val_fmt' => $svc_val_fmt,
      'svc.format' => $mime,
      'rft_id' => $identifier,
    );

    $arguments['svc.rotate'] = $this->get('rotation');

    $size = $this->get('size');

    if ($size) {
      $size = explode(',', $size);
      $width = $size[0];
      $height = $size[1];
      if (!$width && $height) {
      $arguments['svc.scale'] = $height;
      }
    }

    return $this->build_url($service, array('query' => $arguments));

  }

}

$app = new App();