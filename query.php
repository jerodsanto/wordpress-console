<?php
require('common.php');

set_error_handler('console_error_handler');

if (isset($_POST['query'])) {
  $existing_vars = get_defined_vars();

  // restore session variables if they exist
  if (isset($_SESSION['console_vars'])) {
    extract(eval("return " . $_SESSION['console_vars'] . ";"));
  }

  $query = stripslashes($_POST['query']);
  
  // append query to current partial query if there is one
  if (isset($_SESSION['partial'])) {
    $query = $_SESSION['partial'] . $query;
  }

  try {
    if (parse($query) == 0) {
      $response = array();

      // start output buffer (to capture prints)
      ob_start();
      $rval = eval($_SESSION['code']);

      // eval'd code had a return value
      if ($rval != NULL) { 
        $response['rval'] = $rval;
      }
      $response['output'] = ob_get_contents();

      // quietly discard buffered output
      ob_end_clean();
      print json_encode($response);
      // clear the code buffer
      $_SESSION['code'] = '';
      $_SESSION['partial'] = '';
    } else {
      print json_encode(array('output' => 'partial'));
    }
  } catch(Exception $exception) {
    error($exception->getMessage());
  }

  // store variables to session
  $current_vars = get_defined_vars();

  save_variables($existing_vars,
    $current_vars,
    array('query','response','rval','existing_vars','current_vars','_SESSION'));

} else {
  error('Error initializing session.');
}

?>