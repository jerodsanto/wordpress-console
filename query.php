<?php
require('common.php');

@ob_end_clean();
error_reporting(E_ALL);
set_time_limit(0);

require_once "Shell.php";

$__shell = new PHP_Shell();


if (isset($_POST['query'])) {
  $query = trim($_POST['query']);

  try {
    if ($__shell->parse() == 0) {
      ## we have a full command, execute it

      $__shell_retval = eval($__shell->getCode()); 
      if (isset($__shell_retval)) {
        print json_encode(array('result' => $__shell_retval));
      }
    }
  } catch(Exception $__shell_exception) {
    error('Error executing statement.');
  }
} else {
  error('Error initializing session.');
}

?>