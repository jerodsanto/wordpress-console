<?php
require( 'common.php' );

if ( isset($_POST['partial'] ) ) {
  $secret = get_option('wordpress-console-secret');
  if ( !$secret ) {
    return;
  }

  if ( !isset( $_POST['signature'] ) || !$_POST['signature'] ) {
    return;
  }

  $partial = stripslashes( $_POST['partial'] );
  if ( hash_hmac( 'sha1', $partial, $secret ) != $_POST['signature'] ) {
    return;
  }

  if ( !preg_match( '#([0-9a-z_-]+)$#i', $partial, $m ) ) {
    die( json_encode( false ) );
  }

  $candidates = preg_grep( "/^{$m[1]}/", complete( $m[1] ) );
  sort( $candidates );
  die( json_encode( ( array ) $candidates ) );
} else {
  error( 'Error initializing session.' );
}

// returns array of possible matches
function complete( $string ) {
    /**
    * parse the line-buffer backwards to see if we have a
    * - constant
    * - function
    * - variable
    */

    $m = array();

    if ( preg_match( '#\$([A-Za-z0-9_]+)->#', $string, $a ) ) {
        /* check for $o->... */
        $name = $a[1];

        if ( isset( $GLOBALS[$name] ) && is_object( $GLOBALS[$name] ) ) {
            $c = get_class_methods( $GLOBALS[$name] );

            foreach ( $c as $v ) {
                $m[] = $v.'(';
            }
            $c = get_class_vars( get_class( $GLOBALS[$name] ) );

            foreach ( $c as $k => $v ) {
                $m[] = $k;
            }

            return $m;
        }
    } else if ( preg_match( '#\$([A-Za-z0-9_]+)\[([^\]]+)\]->#', $string, $a ) ) {
        /* check for $o[...]->... */
        $name = $a[1];

        if ( isset( $GLOBALS[$name] ) &&
            is_array( $GLOBALS[$name] ) &&
            isset( $GLOBALS[$name][$a[2]] ) ) {

            $c = get_class_methods( $GLOBALS[$name][$a[2]] );

            foreach ( $c as $v ) {
                $m[] = $v.'(';
            }
            $c = get_class_vars( get_class( $GLOBALS[$name][$a[2]] ) );

            foreach ( $c as $k => $v ) {
                $m[] = $k;
            }
            return $m;
        }

    } else if ( preg_match( '#([A-Za-z0-9_]+)::#', $string, $a ) ) {
        /* check for Class:: */
        $name = $a[1];

        if ( class_exists( $name, false ) ) {
            $c = get_class_methods( $name );

            foreach ( $c as $v ) {
                $m[] = sprintf( '%s::%s(', $name, $v );
            }

            $cl = new ReflectionClass( $name );
            $c = $cl->getConstants();

            foreach ( $c as $k => $v ) {
                $m[] = sprintf( '%s::%s', $name, $k );
            }

            return $m;
        }
    } else if ( preg_match( '#\$([a-zA-Z]?[a-zA-Z0-9_]*)$#', $string ) ) {
        $m = array_keys( $GLOBALS );

        return $m;
    } else if ( preg_match( '#new #', $string ) ) {
        $c = get_declared_classes();

        foreach ( $c as $v ) {
            $m[] = $v.'(';
        }

        return $m;
    } else if ( preg_match( '#^:set #', $string ) ) {
        foreach ( PHP_Shell_Options::getInstance()->getOptions() as $v ) {
            $m[] = $v;
        }

        return $m;
    }

    $f = get_defined_functions();

    foreach ( $f['internal'] as $v ) {
        $m[] = $v.'(';
    }

    foreach ( $f['user'] as $v ) {
        $m[] = $v.'(';
    }

    $c = get_declared_classes();

    foreach ( $c as $v ) {
        $m[] = $v.'::';
    }

    $c = get_defined_constants();

    foreach ( $c as $k => $v ) {
        $m[] = $k;
    }

    /* taken from http://de3.php.net/manual/en/reserved.php */
    $m[] = 'abstract';
    $m[] = 'and';
    $m[] = 'array(';
    $m[] = 'as';
    $m[] = 'break';
    $m[] = 'case';
    $m[] = 'catch';
    $m[] = 'class';
    $m[] = 'const';
    $m[] = 'continue';
    # $m[] = 'declare';
    $m[] = 'default';
    $m[] = 'die(';
    $m[] = 'do';
    $m[] = 'echo(';
    $m[] = 'else';
    $m[] = 'elseif';
    $m[] = 'empty(';
    # $m[] = 'enddeclare';
    $m[] = 'eval(';
    $m[] = 'exception';
    $m[] = 'extends';
    $m[] = 'exit(';
    $m[] = 'extends';
    $m[] = 'final';
    $m[] = 'for (';
    $m[] = 'foreach (';
    $m[] = 'function';
    $m[] = 'global';
    $m[] = 'if';
    $m[] = 'implements';
    $m[] = 'include "';
    $m[] = 'include_once "';
    $m[] = 'interface';
    $m[] = 'isset(';
    $m[] = 'list(';
    $m[] = 'new';
    $m[] = 'or';
    $m[] = 'print(';
    $m[] = 'private';
    $m[] = 'protected';
    $m[] = 'public';
    $m[] = 'require "';
    $m[] = 'require_once "';
    $m[] = 'return';
    $m[] = 'static';
    $m[] = 'switch (';
    $m[] = 'throw';
    $m[] = 'try';
    $m[] = 'unset(';
    # $m[] = 'use';
    $m[] = 'var';
    $m[] = 'while';
    $m[] = 'xor';
    $m[] = '__FILE__';
    $m[] = '__FUNCTION__';
    $m[] = '__CLASS__';
    $m[] = '__LINE__';
    $m[] = '__METHOD__';

    return $m;
}

?>
