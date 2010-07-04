<?php
/*
Plugin Name: WordPress Console
Plugin URI: http://github.com/sant0sk1/wordpress-console
Description: An interactive console for WordPress developers
Author: Jerod Santo
Author URI: http://jerodsanto.net
Version: 0.2.3
*/

define( 'WP_CONSOLE_URL', WP_PLUGIN_URL . "/wordpress-console/" );

// THE CSS AND THE JAVASCRIPTS
function console_admin_css() {
  wp_enqueue_style( 'console', WP_CONSOLE_URL . "console.css" );
}

function console_admin_javascripts() {
  wp_enqueue_script( 'console', WP_CONSOLE_URL . 'console.js', array( 'jquery' ) );
  wp_enqueue_script( 'sha1', WP_CONSOLE_URL . 'sha1.js', array( 'jquery' ) );
}

// THE CONSOLE
function console_admin_page() {
  $secret = get_option( 'wordpress-console-secret' );
  if ( !$secret ) {
    $secret = md5( time() . php_uname("n") . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_HOST'] . __FILE__ );
    update_option( 'wordpress-console-secret', $secret );
  }
  ?>
  <script type="text/javascript" charset="utf-8">
    var WP_CONSOLE_VERSION = <?php echo json_encode( '0.2.3' )  ?>;
    var WP_CONSOLE_URL     = <?php echo json_encode( WP_CONSOLE_URL ) ?>;
    var WP_CONSOLE_SECRET  = <?php echo json_encode( $secret ) ?>;
  </script>
  <div class="wrap">
    <h2>WordPress Console: "?" for help menu</h2>
    <div id="wrapper">
    </div>
  </div>
  <?php
}

// THE HOOK-UP
function console_hooks() {
  $page = add_menu_page( 'Console', 'Console', 10, 'console',
    'console_admin_page', WP_CONSOLE_URL . "icon16.png" );
  add_action("admin_print_scripts-$page",'console_admin_javascripts');
  add_action("admin_print_styles-$page", 'console_admin_css');
}

add_action('admin_menu', 'console_hooks');
?>
