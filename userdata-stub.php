<?php
if (__FILE__ == $_SERVER['DOCUMENT_ROOT'].$_SERVER['PHP_SELF']){
  die("Direct access forbidden");
}
class EndomondoUserCredentials {
    private $_UserNameCount = 0;
    private $_PasswordCount = 0;

    public function GetUsername() {
      $_UserNameCount++;
      if ($_UserNameCount > 1) { die("invalid call"); }
      return '#USERNAME#';
    }

    public function GetPassword() {
      $_PasswordCount++;
      if ($_PasswordCount > 1) { die("invalid call"); }      
      return '#PASSWORD#';
    }
  }