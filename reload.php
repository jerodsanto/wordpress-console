<?php
require('common.php');

if (isset($_SESSION['console_vars'])) {
  unset($_SESSION['console_vars']);
}
if (isset($_SESSION['partial'])) {
  unset($_SESSION['partial']);
}

print json_encode(array('output' => "Success!"));
?>