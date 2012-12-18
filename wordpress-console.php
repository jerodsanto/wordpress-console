<?php
/*
Plugin Name: WordPress Console
Plugin URI: http://github.com/sant0sk1/wordpress-console
Description: An interactive console for WordPress developers
Author: Jerod Santo
Author URI: http://jerodsanto.net
Version: 0.3.9
*/

if ( ! function_exists( 'is_ssl' ) ) :
function is_ssl() {
  if ( isset($_SERVER['HTTPS']) ) {
    if ( 'on' == strtolower($_SERVER['HTTPS']) ) {
      return true;
    }
    if ( '1' == $_SERVER['HTTPS'] ) {
      return true;
    }
  } elseif ( isset($_SERVER['SERVER_PORT']) && ( '443' == $_SERVER['SERVER_PORT'] ) ) {
    return true;
  }
  return false;
}
endif;

class WordPressConsole {
  public  $version = '0.3.9';
  private $url;
  private $secret;

  function __construct() {
    if ( version_compare( get_bloginfo( 'version' ) , '3.0' , '<' ) ) {
      $plugin_url = ( get_option( 'siteurl' ) . "/wp-content/plugins" );
    } else {
      $plugin_url = WP_PLUGIN_URL;
    }

    if ( is_ssl() ) {
      $plugin_url = str_replace( 'http://' , 'https://' , $plugin_url );
    }

    $this->url    = $plugin_url . "/" . $this->_get_plugin_directory() . "/";
    $this->secret = $this->get_secret();
    add_action( 'admin_menu', array( &$this, 'init' ) );
  }

  function init() {
    $page = add_menu_page( 'Console', 'Console', 'administrator', 'console',
      array( &$this, 'admin' ), $this->url . "icon16.png" );
    add_action( "admin_print_scripts-$page", array( &$this, 'scripts' ) );
    add_action( "admin_print_styles-$page", array( &$this, 'styles' ) );
  }

  function admin() { ?>
    <script type="text/javascript" charset="utf-8">
      var WP_CONSOLE_VERSION = <?php echo json_encode( $this->version ) ?>;
      var WP_CONSOLE_URL     = <?php echo json_encode( $this->url )     ?>;
      var WP_CONSOLE_SECRET  = <?php echo json_encode( $this->secret )  ?>;
      var WP_ROOT_PATH       = <?php echo json_encode( ABSPATH ) ?>;
    </script>
    <div class="wrap">
      <h2>WordPress Console: "?" for help menu</h2>
      <div id="wrapper">
      </div>
    </div><?php
  }

  function scripts() {
    wp_enqueue_script( 'console', $this->url . 'console.js', array( 'jquery' ) );
    wp_enqueue_script( 'sha1',    $this->url . 'sha1.js',    array( 'jquery' ) );
  }

  function styles() {
    wp_enqueue_style( 'console', $this->url . "console.css" );
  }

  private function get_secret() {
    if ( $secret = get_option( 'wordpress-console-secret' ) ) {
      return $secret;
    }

    $secret = md5( time() * time() );
    update_option( 'wordpress-console-secret', $secret );
    return $secret;
  }

  protected function _get_plugin_directory() {
    $parts = explode( DIRECTORY_SEPARATOR, dirname( __FILE__ ) );
    return array_pop( $parts );
  }

  function WordPressConsole() { // legacy
    $this->__construct();
  }
}

new WordPressConsole;
?>
