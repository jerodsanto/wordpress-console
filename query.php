<?php
require_once( 'common.php' );

set_error_handler( 'console_error_handler' );

$secret = get_option( 'wordpress-console-secret' );
if ( !$secret ) {
  return;
}

if ( !isset( $_POST['signature'] ) || !$_POST['signature'] ) {
  return;
}

if ( !isset( $_POST['query'] ) || !$_POST['query'] ) {
  return;
}

$query = stripslashes( $_POST['query'] );

if ( hash_hmac( 'sha1', $query, $secret ) != $_POST['signature'] ) {
  return;
}

$existing_vars = get_defined_vars();

// restore session variables if they exist
if ( isset( $_SESSION['console_vars'] ) ) {
  extract( eval( "return " . $_SESSION['console_vars'] . ";" ) );
}

// append query to current partial query if there is one
if ( isset( $_SESSION['partial'] ) ) {
  $query = $_SESSION['partial'] . $query;
}

try {
  if ( parse( $query ) == 0 ) {
    $response = array();

    ob_start(); // start output buffer (to capture prints)
    $rval = eval( $_SESSION['code'] );
    $response['output'] = ob_get_contents();
    ob_end_clean(); // quietly discard buffered output

    if ( isset( $rval ) ) {
      ob_start(); // do it again, this time for the return value
      print_r( $rval );
      $response['rval'] = ob_get_contents();
      ob_end_clean();
    }

    // clear the code buffer
    $_SESSION['code']    = '';
    $_SESSION['partial'] = '';

    print json_encode( $response );
  } else {
    print json_encode( array( 'output' => 'partial' ) );
  }
} catch( Exception $exception ) {
  error( $exception->getMessage() );
}

// store variables to session
$current_vars = get_defined_vars();
$ignore = array( 'query','response','rval','existing_vars','current_vars','_SESSION' );

save_variables( $existing_vars, $current_vars, $ignore );
?>
