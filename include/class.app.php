<?php

class App {

  // Djatoka URL
  public static $resolver = 'http://dl-img.home.nyu.edu/adore-djatoka/resolver';

  public static $filesserver = 'http://dlib.nyu.edu/files';
  
  public function __construct($request = NULL) {
    try {
      spl_autoload_register(function ($class) {
        require_once __DIR__ . '/class.' . strtolower($class) . '.php';
      });
      $this->set('arguments', $request);
    }
    catch (Exception $e) {
      throw new Exception($e->getMessage());
    }
  }
  
  public function status_header($setHeader = NULL) {
    static $theHeader = NULL;
    //if we already set it, then return what we set before (can't set it twice anyway)
    if ($theHeader) {
      return $theHeader;
    }
    $theHeader = $setHeader;
    header("HTTP/1.1 $setHeader");
    return $setHeader;
  }  

  public function set($name, $value) {
    if (method_exists($this, ($method = 'set_' . $name))) {
      $this->$method($value);
    }
    else return;    
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
  
  public function output() {
    $url = $this->get('url');
    $alter = $this->get('alter');
    if ($alter) {
      $image = new Image($url);
      $width = $this->get('width');
      $height = $this->get('height');
      if ($width && $height) {
        $image->resize($width, $height);
      }
      else {
        $image->resizeToWidth($width);
      }
      $image->output($image);  
    }
    // Use Djakota to scale using height or for "max" request
    // This and all future requests should be directed to the given URI.
    else {
      status_header(301);
      header("Location: $url");
    }
  }  
  
  /**
   * Getters
   */

  public function get_arguments() {
    return $this->arguments;
  }

  public function get_service() {
    return $this->service;
  }

  public function get_identifier() {
    return $this->identifier;
  }  

  /**
   * The region parameter defines the rectangular portion of the
   * full image to be returned. Region can be specified by pixel 
   * coordinates, percentage or by the value “full”, which 
   * specifies that the entire image should be returned.
   * See http://iiif.io/api/image/2.1/#region
   */
  public function get_region() {
    return $this->region;
  }

  /**
   * The size parameter determines the dimensions to which 
   * the extracted region is to be scaled.   
   * See http://iiif.io/api/image/2.1/#size
   */
  public function get_size() {
     return $this->size;
   }

  public function get_rotation() {
    return $this->rotation;
  }
  
  /**
   * The quality parameter determines whether the image is 
   * delivered in color, grayscale or black and white.
   * See http://iiif.io/api/image/2.1/#quality
   */
  public function get_quality() {
    return $this->quality;
  }
  
  public function get_alter() {
    return $this->alter;
  }
  
  public function get_height() {
    return $this->height;
  }

  public function get_width() {
    return $this->width;
  }  
  
  public function get_services() {
    return array(
      'books',
      'photos',
      'maps',
      'images',
    );
  }
  
  public function get_regions() {
    // Looking forward to use Loris, in the meantime we only "implement" full
    return array(
      'full',
    );
  }
  
  public function get_qualities() {
    return array(
      'color', // The image is returned in full color. 
      'gray', // The image is returned in grayscale, where each pixel is black, white or any shade of gray in between.
      'bitonal', // The image returned is bitonal, where each pixel is either black or white.
      'default', // The image is returned using the server’s default quality (e.g. color, gray or bitonal) for the image.
    );
  }

  public function get_url() {

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
        
    // Djatoka URL
    $service = $this->get('resolver');
    
    $filesserver = $this->get('filesserver');
    
    // Service to request a Region
    $svc_id = 'info:lanl-repo/svc/getRegion';
    
    // OpenURL
    // http://www.niso.org/apps/group_public/document.php?document_id=14831
    $url_ver = 'Z39.88-2004';
      
    // Metadata Format specifying parameters to request a Region
    $svc_val_fmt = 'info:ofi/fmt:kev:mtx:jpeg2000';
      
    // Mime type of the image format to be provided as response.
    $mime = 'image/jpeg';
    
    // file identifier
    $identifier =  "$filesserver/$prefix/$resource.$format";
    
    $arguments = array(
      'url_ver' => $url_ver,
      'svc_id' => $svc_id,
      'svc_val_fmt' => $svc_val_fmt,
      'svc.format' => $mime,
      'rft_id' => $identifier,
    );
    
    // Rotates image by 90/180/270 degrees clockwise.
    $arguments['svc.rotate'] = $this->get('rotation');

    $width = $this->get('width');
    
    $height = $this->get('height');
    
    if (!$width && $height) {
      $arguments['svc.scale'] = $height;
    }

    // Options
    $options = array('query' => $arguments);

    $service .= (strpos($service, '?') !== FALSE ? '&' : '?') . http_build_query($options['query']);

    return $service;

  }
  
  public function get_resolver() {
    return self::$resolver;
  }
    
  public function get_filesserver() {
    return self::$filesserver;
  }  

  /**
   * Setters
   * {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
   */

  private function set_service($service) {
    // get a list of available services
    $services = $this->get('services');
    if (in_array($service, $services)) $this->service = $service;
    else {
      throw new Exception("Invalid service $service.");
    }
  }

  private function set_identifier($identifier) {
    $test = $identifier;
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
    $this->identifier = $identifier;
  }
  
  private function set_region($region) {
    $regions = $this->get('regions');
    if (in_array($region, $regions)) $this->region = $region;
    // region not available
    else {
      throw new Exception("Invalid region $region");
    }
  }
  
  private function set_size($size) {
    switch ($size) {
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
         $this->size = FALSE; // if no size, Djatoka Image Server will return the original size
         $this->alter = FALSE;         
         break;
       /**
        * Test w, OR ,h ORO w,h
        */
       default:
        preg_match('/^(\d*),{1}$|^,{1}(\d*)$|^(\d*),{1}(\d*)$/', $size, $matches);
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
        if (isset($matches[0])) {

          $this->size = $matches[0];
          // we can use the first character to check the type of request
          // if size has a comma (,) as the the first charater, we need
          // resize by height, otherwise by width
          $first_character = $matches[0][0];
          
          $this->alter = ($first_character == ',') ? FALSE : TRUE;
          
          $size = explode(',', $size);
      
          $this->width = $size[0];
          
          $this->height = $size[1];
          
        }
        else {
          throw new Exception("Invalid size.");
        }
        break;
      }
  }

  private function set_rotation($input) {
    // rotates image by 0/90/180/270 degrees clockwise.
    if (is_numeric($input)) {
      $rotation = (int) $input;
      if ($rotation < 90) {
        $this->rotation = 0;      
      }
      elseif ($rotation >= 90 && $rotation < 180) {
        $this->rotation = 90;
      }
      elseif ($rotation >= 180 && $rotation < 270) {
        $this->rotation = 180;
      }
      elseif ($rotation >= 270) {
        $this->rotation = 270;
      }
    }
    else {
      throw new Exception("Invalid roation");
    }
  }
  
  private function set_quality($input) {
    $pathinfo = pathinfo($input);
    if (isset($pathinfo['extension'])) {
      $qualities = $this->get('qualities');
      $quality = $pathinfo['filename'];
      if (in_array($quality, $qualities)) $this->quality = $pathinfo['basename'];
      // region not available
      else {
        throw new Exception("Invalid quality $quality");
      }
    }
  }

  private function set_arguments($request = null) {
  
    // $_SERVER['SCRIPT_NAME'] can, in contrast to $_SERVER['PHP_SELF'], not be modified by a visitor.
    if ($dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '\/')) {
      $base_path = "$dir/";
    }
    else {
      $base_path = '/';
    }
      
    if ($request) {
      $parse_url = parse_url($request);
      $request_uri = $parse_url['path'];
    }
    else {
      $request_uri = $_SERVER['REQUEST_URI'];
    }

    $uri = filter_var($request_uri, FILTER_SANITIZE_STRING);
      
    // remove $base_path from uri
    if (0 === strpos($uri, $base_path)) {
      $uri = substr($uri, strlen($base_path));
    }
    
    $arguments = explode('/', $uri);
    
    // https://stackoverflow.com/questions/3654295/remove-empty-array-elements
    // https://stackoverflow.com/questions/3401850/after-array-filter-how-can-i-reset-the-keys-to-go-in-numerical-order-starting    
    $arguments = array_values(array_filter($arguments, function($value) { return $value !== ''; }));

    // We required 6 arguments. Don't waste time is we don't have them.
    if (count($arguments) != 6) {
      throw new Exception("Bad Request.");
    }
    
    // Set idividual arguments
    // {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}

    // Service
    // Should be the 1st value in argument array
    $this->set('service', $arguments[0]);
    
    // Identifier
    // Should be the 2nd value in argument array
    $this->set('identifier', $arguments[1]);
    
    // Region
    // Should be the 3rd value in argument array
    $this->set('region', $arguments[2]);
        
    // Size
    // Should be the 4rd value in argument array
    $this->set('size', $arguments[3]);

    // Rotation
    // Should be the 5th value in argument array
    $this->set('rotation', $arguments[4]);

    // Quality
    // Should be the 6th value in argument array
    $this->set('quality', $arguments[5]);

    $this->arguments = array(
      'service' => $this->get('service'),
      'identifier' => $this->get('identifier'),
      'region' => $this->get('region'),
      'size' => $this->get('size'),
      'rotation' => $this->get('rotation'),
      'quality' => $this->get('quality'),    
    );
  }

}
