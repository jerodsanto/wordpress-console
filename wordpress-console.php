<?php
/*
Plugin Name: WordPress Console
Plugin URI: http://github.com/sant0sk1/wordpress-console
Description: An interactive console for WordPress developers
Author: Jerod Santo
Author URI: http://jerodsanto.net
Version: 0.1.0
*/

// THE CSS AND THE JAVASCRIPTS
function console_add_headers() {
  $css_url = WP_PLUGIN_URL . '/wordpress-console/console.css';
  $js_url  = WP_PLUGIN_URL . '/wordpress-console/console.js';
  echo '<link rel="stylesheet" type="text/css" href="' . $css_url . '" />';
  echo '<script type="text/javascript" src="' . $js_url .'" ></script>';
}

// THE CONSOLE
function console_admin_page() {
  $page_url = str_replace( '%7E', '~', $_SERVER['REQUEST_URI']);
  // session_start();
  
  echo '<div id="wrap">';
  echo '<h2>WordPress Console: "?" or "help"</h2>';
  echo '<div id="wrapper">';
  echo '</div>';
  echo '</div>';
}

// THE HOOK-UP
add_action('admin_menu', 'console_add_page');
add_action('admin_head', 'console_add_headers');

function console_add_page() {
  add_management_page('Console', 'Console', 10, __FILE__, 'console_admin_page');
}
?>