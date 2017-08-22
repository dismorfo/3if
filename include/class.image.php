<?php

/*
 * Link: http://www.white-hat-web-design.co.uk/articles/php-image-resizing.php
 */

class Image {

  public function __construct($url) {
    $this->load($url);
  }
  
  function load($url) {
    $info = getimagesize($url);
    $this->type = $info[2];
    $this->mime = $info['mime'];
    if ($this->type == IMAGETYPE_JPEG) {
      $this->image = imagecreatefromjpeg($url);
    }
  }
   
  function output() {
    header("Content-type: $this->mime");
    imagejpeg($this->image);
    imagedestroy($this->image);
  }
   
  function width() {
    return imagesx($this->image);
  }
   
  function height() {
    return imagesy($this->image);
  }
   
  function resizeToHeight($height) {
    $ratio = $height / $this->height();
    $width = $this->width() * $ratio;
    $this->resize($width, $height);
  }
   
  function resizeToWidth($width) {
    $ratio = $width / $this->width();
    $height = $this->height() * $ratio;
    $this->resize($width, $height);
  }

  function scale($scale) {
    $width = $this->width() * $scale/100;
    $height = $this->height() * $scale/100;
    $this->resize($width, $height);
  }

  function resize($width, $height) {
    $image = imagecreatetruecolor($width, $height);
    imagecopyresampled($image, $this->image, 0, 0, 0, 0, $width, $height, $this->width(), $this->height());
    $this->image = $image;
  }
    
  // http://php.net/manual/en/function.imagefilter.php
  function grayscale() {    
    imagefilter($this->image, IMG_FILTER_GRAYSCALE);
  }

  // http://php.net/manual/en/function.imagefilter.php
  // https://stackoverflow.com/questions/254388/how-do-you-convert-an-image-to-black-and-white-in-php
  function bitonal() {    
    $this->grayscale();
    imagefilter($this->image, IMG_FILTER_CONTRAST, -100);
  }
    
  function destroy() {
    imagedestroy($this->image);
  }
}
