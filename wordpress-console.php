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
function console_admin_css() {
  wp_enqueue_style('console',WP_PLUGIN_URL . "/wordpress-console/console.css");
}

function console_admin_javascripts() {
  wp_enqueue_script('console', WP_PLUGIN_URL . '/wordpress-console/console.js', array('jquery'));
}

// THE CONSOLE
function console_admin_page() {
  $page_url = str_replace( '%7E', '~', $_SERVER['REQUEST_URI']);
  
  echo '<div id="wrap">';
  echo '<h2>WordPress Console: "?" for help menu</h2>';
  echo '<div id="wrapper">';
  echo '</div>';
  echo '</div>';
}

// THE HOOK-UP
function console_hooks() {
  $page = add_management_page('Console', 'Console', 10, __FILE__, 'console_admin_page');
  add_action("admin_print_scripts-$page",'console_admin_javascripts');
  add_action("admin_print_styles-$page", 'console_admin_css');
}

add_action('admin_menu', 'console_hooks');
?>