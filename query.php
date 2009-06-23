<?php
require('common.php');

if (isset($_POST['query'])) {
  $query = trim($_POST['query']);
  
  
  print json_encode(array(
    'result' => $query
    ));
} else {
  error('Error initializing session.');
}

?>