<?php
require('common.php');

if (isset($_SESSION['console_vars'])) {
  unset($_SESSION['console_vars']);
}
if (isset($_SESSION['partial'])) {
  unset($_SESSION['partial']);
}
if (isset($_SESSION['code'])) {
  unset($_SESSION['code']);
}
print json_encode(array('output' => "Success!"));
?>