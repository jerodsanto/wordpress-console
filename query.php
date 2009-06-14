<?php
require('common.php');

@ob_end_clean();
error_reporting(E_ALL);
set_time_limit(0);


if (isset($_POST['query'])) {
  $query = stripslashes($_POST['query']);

  if (parse($query) == 0) {
    $response = array('return' => '', 'output'=> '');
    
    ob_start(); // start output buffer (to capture prints)
    $rval = eval($_SESSION['code']);
    
    if ($rval != NULL) { // eval'd code had a return value
      $response['return'] = $rval;
    }
    $response['output'] = ob_get_contents();
    
    ob_end_clean(); // quietly discard buffered output
    print json_encode($response);
    $_SESSION['code'] = ''; // clear the code buffer
  } else {
    print json_encode(array('output' => 'not a complete statement'));
  }
  
} else {
  error('Error initializing session.');
}

?>