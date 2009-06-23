<?php
require('common.php');

if (isset($_POST['query'])) {
  $query = stripslashes($_POST['query']);

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
    } else {
      print json_encode(array('output' => 'not a complete statement'));
    }
  } catch(Exception $exception) {
    error($exception->getMessage());
  }

} else {
  error('Error initializing session.');
}

?>