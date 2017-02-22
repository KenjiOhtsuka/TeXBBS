<?php
session_start();
  $DB = "math_bbs";
  $myCon = mysqli_connect(
    'localhost',
    'root', 
    'root'
  );
  if ($myCon == false) die("Connection failed.");
  if (!mysqli_select_db($myCon, $DB)) die("Selection failed.");
  mysqli_set_charset ($myCon,  "utf8" );
?>
