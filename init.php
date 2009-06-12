<?php
require('common.php');

if (isset($_POST['init'])) {
  global $current_user;
  get_currentuserinfo();
  
  print json_encode(array(
    'user'        => $current_user->user_login,
    'wp_version'  => 'wp-' + get_bloginfo('version'),
    'PHPSESSID'   => htmlspecialchars(session_id())
    ));
} else {
  error('Error initializing session.');
}

?>