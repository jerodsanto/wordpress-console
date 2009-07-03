<?php
/*
Plugin Name: WordPress Console
Plugin URI: http://github.com/sant0sk1/wordpress-console
Description: An interactive console for WordPress developers
Author: Jerod Santo
Author URI: http://jerodsanto.net
Version: 0.1.2
*/

// THE CSS AND THE JAVASCRIPTS
function console_admin_css() {
  wp_enqueue_style('console',WP_PLUGIN_URL . "/wordpress-console/console.css");
}

function console_admin_javascripts() {
  wp_enqueue_script('console', WP_PLUGIN_URL . '/wordpress-console/console.js', array('jquery'));
  wp_enqueue_script('sha1', WP_PLUGIN_URL . '/wordpress-console/sha1.js', array('jquery'));
}

// THE CONSOLE
function console_admin_page() {
  $secret = get_option('wordpress-console-secret');
  if ( !$secret ) {
	  $secret = md5( time() . php_uname("n") . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_HOST'] . __FILE__ );
	  update_option('wordpress-console-secret', $secret);
  }
  $complete = get_option('wordpress-console-tabcomplete');
  if ( false === $complete || null === $complete ) {
	  update_option('wordpress-console-tabcomplete', 0);
	  $complete = 0;
  }
  $page_url = str_replace( '%7E', '~', $_SERVER['REQUEST_URI']);
  echo '<input type="hidden" id="wpconsolesecret" value="'.$secret.'"';
  echo '<input type="hidden" id="wpconsoletabcomplete" value="'.$complete.'"';
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
