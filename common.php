<?php
# try to load the wordpress environment from passed in location known by the client
# otherwise, fall back to the mostly (but sometimes not) safe assumption that it is
# up 3 directories from this file.
if ( isset( $_POST["root"] ) && is_dir( $_POST["root"] ) ) {
  require_once( $_POST["root"] . "/wp-load.php" );
} else {
  require_once( dirname( __FILE__ ) . "/../../../wp-load.php" );
}

require_once( ABSPATH . "wp-admin/includes/admin.php" );

if ( !session_id() ) {
  session_start();
}

if ( ob_get_length() > 0 ) {
  ob_end_clean();
}

error_reporting( E_ALL ^ E_PARSE );
set_time_limit( 0 );

if ( !function_exists( "json_encode" ) ) {
  function json_encode( $value ) {
    require_once( "lib/FastJSON.class.php" );
    return FastJSON::encode($value);
  }
}

function console_error_handler( $errno, $errorstr ) {
  error( $errorstr );
}

function error( $error ) {
  exit( json_encode( array( "error" => $error ) ) );
}

function logit( $msg ) {
  $file = "/tmp/console.log";
  $fh = fopen($file,"a");
  fwrite($fh,$msg);
  fwrite($fh,"\n\n");
  fclose($fh);
}

// saves newly defined variables to session.
// somebody please refactor this!
function save_variables( $existing, $current, $ignore ) {
  $new_vars  = array_diff( array_keys( $current ), array_keys( $existing ) );
  $user_vars = array_diff( $new_vars, $ignore );

  $save_vars = array();

  foreach( $current as $key => $value ) {
    if ( in_array( $key, $user_vars ) ) {
      $save_vars[$key] = $value;
    }
  }

  $export = var_export( $save_vars, true );
  // special consideration for variables that are objects
  // see: http://www.thoughtlabs.com/2008/02/02/phps-mystical-__set_state-method/
  $export  = preg_replace_callback( "/(\w+)::__set_state/Ums", "class_set_state_check", $export );
  $_SESSION["console_vars"] = $export;
}

// classes to be restored need to implement __set_state() function.
// if they don't have it, we will convert to stdClass object.
function class_set_state_check($matches) {
  if (method_exists($matches[1], "__set_state")) {
    return $matches[0];
  } else {
    return "(object) ";
  }
}

// this function was yoinked (and adjusted) from the 'php shell' project. See:
// http://jan.kneschke.de/projects/php-shell
// return int 0 if a executable statement is in the session buffer, non-zero otherwise
function parse( $code ) {
    ## remove empty lines
    if (trim($code) == "") return 1;

    $t = token_get_all("<?php ".$code." ?>");
    // logit($code);

    $need_semicolon = 1; /* do we need a semicolon to complete the statement ? */
    $need_return = 1;    /* can we prepend a return to the eval-string ? */
    $open_comment = 0;   /* a open multi-line comment */
    $eval = "";          /* code to be eval()'ed later */
    $braces = array();   /* to track if we need more closing braces */

    $methods = array();  /* to track duplicate methods in a class declaration */
    $ts = array();       /* tokens without whitespaces */

    foreach ($t as $ndx => $token) {
        if (is_array($token)) {
            $ignore = 0;

            switch($token[0]) {
            case T_WHITESPACE:
            case T_OPEN_TAG:
            case T_CLOSE_TAG:
                $ignore = 1;
                break;
            case T_FOREACH:
            case T_DO:
            case T_WHILE:
            case T_FOR:

            case T_IF:
            case T_RETURN:

            case T_CLASS:
            case T_FUNCTION:
            case T_INTERFACE:

            case T_PRINT:
            case T_ECHO:

            case T_COMMENT:
            case T_UNSET:

            case T_INCLUDE:
            case T_REQUIRE:
            case T_INCLUDE_ONCE:
            case T_REQUIRE_ONCE:
            case T_TRY:
            case T_SWITCH:
            case T_DEFAULT:
            case T_CASE:
            case T_BREAK:
            case T_DOC_COMMENT:
                $need_return = 0;
                break;
            case T_EMPTY:
            case T_ISSET:
            case T_EVAL:
            case T_EXIT:

            case T_VARIABLE:
            case T_STRING:
            case T_NEW:
            case T_EXTENDS:
            case T_IMPLEMENTS:
            case T_OBJECT_OPERATOR:
            case T_DOUBLE_COLON:
            case T_INSTANCEOF:

            case T_CATCH:
            case T_THROW:

            case T_ELSE:
            case T_AS:
            case T_LNUMBER:
            case T_DNUMBER:
            case T_CONSTANT_ENCAPSED_STRING:
            case T_ENCAPSED_AND_WHITESPACE:
            case T_CHARACTER:
            case T_ARRAY:
            case T_DOUBLE_ARROW:

            case T_CONST:
            case T_PUBLIC:
            case T_PROTECTED:
            case T_PRIVATE:
            case T_ABSTRACT:
            case T_STATIC:
            case T_VAR:

            case T_INC:
            case T_DEC:
            case T_SL:
            case T_SL_EQUAL:
            case T_SR:
            case T_SR_EQUAL:

            case T_IS_EQUAL:
            case T_IS_IDENTICAL:
            case T_IS_GREATER_OR_EQUAL:
            case T_IS_SMALLER_OR_EQUAL:

            case T_BOOLEAN_OR:
            case T_LOGICAL_OR:
            case T_BOOLEAN_AND:
            case T_LOGICAL_AND:
            case T_LOGICAL_XOR:
            case T_MINUS_EQUAL:
            case T_PLUS_EQUAL:
            case T_MUL_EQUAL:
            case T_DIV_EQUAL:
            case T_MOD_EQUAL:
            case T_XOR_EQUAL:
            case T_AND_EQUAL:
            case T_OR_EQUAL:

            case T_FUNC_C:
            case T_CLASS_C:
            case T_LINE:
            case T_FILE:

            case T_BOOL_CAST:
            case T_INT_CAST:
            case T_STRING_CAST:

                /* just go on */
                break;
            default:
                /* debug unknown tags*/
                error_log(sprintf("unknown tag: %d (%s): %s".PHP_EOL, $token[0], token_name($token[0]), $token[1]));

                break;
            }
            if (!$ignore) {
                $eval .= $token[1]." ";
                $ts[] = array("token" => $token[0], "value" => $token[1]);
            }
        } else {
            $ts[] = array("token" => $token, "value" => "");

            $last = count($ts) - 1;

            switch ($token) {
            case "(":
                /* walk backwards through the tokens */

                if ($last >= 4 &&
                    $ts[$last - 1]["token"] == T_STRING &&
                    $ts[$last - 2]["token"] == T_OBJECT_OPERATOR &&
                    $ts[$last - 3]["token"] == ")" ) {
                    /* func()->method()
                    *
                    * we can't know what func() is return, so we can't
                    * say if the method() exists or not
                    *
                    */
                } else if ($last >= 3 &&
                    $ts[0]["token"] != T_CLASS && /* if we are not in a class definition */
                    $ts[0]["token"] != T_ABSTRACT && /* if we are not in a class definition */
                    $ts[1]["token"] != T_CLASS && /* if we are not in a class definition */
                    $ts[$last - 1]["token"] == T_STRING &&
                    $ts[$last - 2]["token"] == T_OBJECT_OPERATOR &&
                    $ts[$last - 3]["token"] == T_VARIABLE ) {

                    /* $object->method( */

                    /* catch (Exception $e) does not set $e in $GLOBALS[] */
                    $in_catch = 0;

                    foreach ($ts as $v) {
                        if ($v["token"] == T_CATCH) {
                            $in_catch = 1;
                        }
                    }

                    if (!$in_catch) {
                        /* $object has to exist and has to be a object */
                        $objname = $ts[$last - 3]["value"];

                        if (!isset($GLOBALS[ltrim($objname, "$")])) {
                            throw new Exception(sprintf('Variable \'%s\' is not set', $objname));
                        }
                        $object = $GLOBALS[ltrim($objname, "$")];

                        if (!is_object($object)) {
                            throw new Exception(sprintf('Variable \'%s\' is not a class', $objname));
                        }

                        $method = $ts[$last - 1]["value"];

                        /* obj */

                        if (!method_exists($object, $method)) {
                            throw new Exception(sprintf("Variable %s (Class '%s') doesn't have a method named '%s'",
                                $objname, get_class($object), $method));
                        }
                    }
                } else if ($last >= 3 &&
                    $ts[0]["token"] != T_CLASS && /* if we are not in a class definition */
                    $ts[$last - 1]["token"] == T_VARIABLE &&
                    $ts[$last - 2]["token"] == T_OBJECT_OPERATOR &&
                    $ts[$last - 3]["token"] == T_VARIABLE ) {

                    /* $object->$method( */

                    /* $object has to exist and has to be a object */
                    $objname = $ts[$last - 3]["value"];

                    if (!isset($GLOBALS[ltrim($objname, "$")])) {
                        throw new Exception(sprintf('Variable \'%s\' is not set', $objname));
                    }
                    $object = $GLOBALS[ltrim($objname, "$")];

                    if (!is_object($object)) {
                        throw new Exception(sprintf('Variable \'%s\' is not a class', $objname));
                    }

                    $methodname = $ts[$last - 1]["value"];

                    if (!isset($GLOBALS[ltrim($methodname, "$")])) {
                        throw new Exception(sprintf('Variable \'%s\' is not set', $methodname));
                    }
                    $method = $GLOBALS[ltrim($methodname, "$")];

                    /* obj */

                    if (!method_exists($object, $method)) {
                        throw new Exception(sprintf("Variable %s (Class '%s') doesn't have a method named '%s'",
                            $objname, get_class($object), $method));
                    }

                } else if ($last >= 6 &&
                    $ts[0]["token"] != T_CLASS && /* if we are not in a class definition */
                    $ts[$last - 1]["token"] == T_STRING &&
                    $ts[$last - 2]["token"] == T_OBJECT_OPERATOR &&
                    $ts[$last - 3]["token"] == "]" &&
                        /* might be anything as index */
                    $ts[$last - 5]["token"] == "[" &&
                    $ts[$last - 6]["token"] == T_VARIABLE ) {

                    /* $object[...]->method( */

                    /* $object has to exist and has to be a object */
                    $objname = $ts[$last - 6]["value"];

                    if (!isset($GLOBALS[ltrim($objname, "$")])) {
                        throw new Exception(sprintf('Variable \'%s\' is not set', $objname));
                    }
                    $array = $GLOBALS[ltrim($objname, "$")];

                    if (!is_array($array)) {
                        throw new Exception(sprintf('Variable \'%s\' is not a array', $objname));
                    }

                    $andx = $ts[$last - 4]["value"];

                    if (!isset($array[$andx])) {
                        throw new Exception(sprintf('%s[\'%s\'] is not set', $objname, $andx));
                    }

                    $object = $array[$andx];

                    if (!is_object($object)) {
                        throw new Exception(sprintf('Variable \'%s\' is not a class', $objname));
                    }

                    $method = $ts[$last - 1]["value"];

                    /* obj */

                    if (!method_exists($object, $method)) {
                        throw new Exception(sprintf("Variable %s (Class '%s') doesn't have a method named '%s'",
                            $objname, get_class($object), $method));
                    }

                } else if ($last >= 3 &&
                    $ts[0]["token"] != T_CLASS && /* if we are not in a class definition */
                    $ts[$last - 1]["token"] == T_STRING &&
                    $ts[$last - 2]["token"] == T_DOUBLE_COLON &&
                    $ts[$last - 3]["token"] == T_STRING ) {

                    /* Class::method() */

                    /* $object has to exist and has to be a object */
                    $classname = $ts[$last - 3]["value"];

                    if (!class_exists($classname)) {
                        throw new Exception(sprintf('Class \'%s\' doesn\'t exist', $classname));
                    }

                    $method = $ts[$last - 1]["value"];

                    if (!in_array($method, get_class_methods($classname))) {
                        throw new Exception(sprintf("Class '%s' doesn't have a method named '%s'",
                            $classname, $method));
                    }
                } else if ($last >= 3 &&
                    $ts[0]["token"] != T_CLASS && /* if we are not in a class definition */
                    $ts[$last - 1]["token"] == T_VARIABLE &&
                    $ts[$last - 2]["token"] == T_DOUBLE_COLON &&
                    $ts[$last - 3]["token"] == T_STRING ) {

                    /* $var::method() */

                    /* $object has to exist and has to be a object */
                    $classname = $ts[$last - 3]["value"];

                    if (!class_exists($classname)) {
                        throw new Exception(sprintf('Class \'%s\' doesn\'t exist', $classname));
                    }

                    $methodname = $ts[$last - 1]["value"];

                    if (!isset($GLOBALS[ltrim($methodname, "$")])) {
                        throw new Exception(sprintf('Variable \'%s\' is not set', $methodname));
                    }
                    $method = $GLOBALS[ltrim($methodname, "$")];

                    if (!in_array($method, get_class_methods($classname))) {
                        throw new Exception(sprintf("Class '%s' doesn't have a method named '%s'",
                            $classname, $method));
                    }

                } else if ($last >= 2 &&
                    $ts[0]["token"] != T_CLASS && /* if we are not in a class definition */
                    $ts[$last - 1]["token"] == T_STRING &&
                    $ts[$last - 2]["token"] == T_NEW ) {

                    /* new Class() */

                    /* don't care about this in a class ... { ... } */

                    $classname = $ts[$last - 1]["value"];

                    if (!class_exists($classname)) {
                        throw new Exception(sprintf('Class \'%s\' doesn\'t exist', $classname));
                    }

                    $r = new ReflectionClass($classname);

                    if ($r->isAbstract()) {
                        throw new Exception(sprintf("Can't instantiate abstract Class '%s'", $classname));
                    }

                    if (!$r->isInstantiable()) {
                        throw new Exception(sprintf('Class \'%s\' can\'t be instantiated. Is the class abstract ?', $classname));
                    }

                } else if ($last >= 2 &&
                    $ts[0]["token"] != T_CLASS && /* if we are not in a class definition */
                    $ts[$last - 1]["token"] == T_STRING &&
                    $ts[$last - 2]["token"] == T_FUNCTION ) {

                    /* make sure we are not a in class definition */

                    /* function a() */

                    $func = $ts[$last - 1]["value"];

                    if (function_exists($func)) {
                        throw new Exception(sprintf('Function \'%s\' is already defined', $func));
                    }
                } else if ($last >= 4 &&
                    $ts[0]["token"] == T_CLASS &&
                    $ts[1]["token"] == T_STRING &&
                    $ts[$last - 1]["token"] == T_STRING &&
                    $ts[$last - 2]["token"] == T_FUNCTION ) {

                    /* make sure we are not a in class definition */

                    /* class a { .. function a() ... } */

                    $func = $ts[$last - 1]["value"];
                    $classname = $ts[1]["value"];

                    if (isset($methods[$func])) {
                        throw new Exception(sprintf("Can't redeclare method '%s' in Class '%s'", $func, $classname));
                    }

                    $methods[$func] = 1;

                } else if ($last >= 1 &&
                    $ts[0]["token"] != T_CLASS && /* if we are not in a class definition */
                    $ts[0]["token"] != T_ABSTRACT && /* if we are not in a class definition */
                    $ts[1]["token"] != T_CLASS && /* if we are not in a class definition */
                    $ts[$last - 1]["token"] == T_STRING ) {
                    /* func() */
                    $funcname = $ts[$last - 1]["value"];

                    if (!function_exists($funcname)) {
                        throw new Exception(sprintf("Function %s() doesn't exist", $funcname));
                    }
                } else if ($last >= 1 &&
                    $ts[0]["token"] != T_CLASS && /* if we are not in a class definition */
                    $ts[$last - 1]["token"] == T_VARIABLE ) {

                    /* $object has to exist and has to be a object */
                    $funcname = $ts[$last - 1]["value"];

                    if (!isset($GLOBALS[ltrim($funcname, "$")])) {
                        throw new Exception(sprintf('Variable \'%s\' is not set', $funcname));
                    }
                    $func = $GLOBALS[ltrim($funcname, "$")];

                    if (!function_exists($func)) {
                        throw new Exception(sprintf("Function %s() doesn't exist", $func));
                    }

                }

                array_push($braces, $token);
                break;
            case "{":
                $need_return = 0;

                if ($last >= 2 &&
                    $ts[$last - 1]["token"] == T_STRING &&
                    $ts[$last - 2]["token"] == T_CLASS ) {

                    /* class name { */

                    $classname = $ts[$last - 1]["value"];

                    if (class_exists($classname, false)) {
                        throw new Exception(sprintf("Class '%s' can't be redeclared", $classname));
                    }
                } else if ($last >= 4 &&
                    $ts[$last - 1]["token"] == T_STRING &&
                    $ts[$last - 2]["token"] == T_EXTENDS &&
                    $ts[$last - 3]["token"] == T_STRING &&
                    $ts[$last - 4]["token"] == T_CLASS ) {

                    /* class classname extends classname { */

                    $classname = $ts[$last - 3]["value"];
                    $extendsname = $ts[$last - 1]["value"];

                    if (class_exists($classname, false)) {
                        throw new Exception(sprintf("Class '%s' can't be redeclared",
                            $classname));
                    }
                    if (!class_exists($extendsname, true)) {
                        throw new Exception(sprintf("Can't extend '%s' ... from not existing Class '%s'",
                            $classname, $extendsname));
                    }
                } else if ($last >= 4 &&
                    $ts[$last - 1]["token"] == T_STRING &&
                    $ts[$last - 2]["token"] == T_IMPLEMENTS &&
                    $ts[$last - 3]["token"] == T_STRING &&
                    $ts[$last - 4]["token"] == T_CLASS ) {

                    /* class name implements interface { */

                    $classname = $ts[$last - 3]["value"];
                    $implements = $ts[$last - 1]["value"];

                    if (class_exists($classname, false)) {
                        throw new Exception(sprintf("Class '%s' can't be redeclared",
                            $classname));
                    }
                    if (!interface_exists($implements, false)) {
                        throw new Exception(sprintf("Can't implement not existing Interface '%s' for Class '%s'",
                            $implements, $classname));
                    }
                }

                array_push($braces, $token);
                break;
            case "}":
                $need_return = 0;
            case ")":
                array_pop($braces);
                break;
            case "[":
                if ($ts[0]["token"] != T_CLASS && /* if we are not in a class definition */
                    $ts[0]["token"] != T_ABSTRACT && /* if we are not in a class definition */
                    $ts[1]["token"] != T_CLASS && /* if we are not in a class definition */
                    $ts[$last - 1]["token"] == T_VARIABLE) {
                    /* $a[] only works on array and string */

                    /* $object has to exist and has to be a object */
                    $objname = $ts[$last - 1]["value"];

                    if (!isset($GLOBALS[ltrim($objname, "$")])) {
                        throw new Exception(sprintf('Variable \'%s\' is not set', $objname));
                    }
                    $obj = $GLOBALS[ltrim($objname, "$")];

                    if (is_object($obj)) {
                        throw new Exception(sprintf('Objects (%s) don\'t support array access operators', $objname));
                    }
                }
                break;
            }

            $eval .= $token;
        }
    }

    $last = count($ts) - 1;
    if ($last >= 2 &&
        $ts[$last - 0]["token"] == T_STRING &&
        $ts[$last - 1]["token"] == T_DOUBLE_COLON &&
        $ts[$last - 2]["token"] == T_STRING ) {

        /* Class::constant */

        /* $object has to exist and has to be a object */
        $classname = $ts[$last - 2]["value"];

        if (!class_exists($classname)) {
            throw new Exception(sprintf('Class \'%s\' doesn\'t exist', $classname));
        }

        $constname = $ts[$last - 0]["value"];

        $c = new ReflectionClass($classname);
        if (!$c->hasConstant($constname)) {
            throw new Exception(sprintf("Class '%s' doesn't have a constant named '%s'",
                $classname, $constname));
        }
    } else if ($last == 0 &&
        $ts[$last - 0]["token"] == T_VARIABLE ) {

        /* $var */

        $varname = $ts[$last - 0]["value"];

        if (!isset($GLOBALS[ltrim($varname, "$")])) {
            throw new Exception(sprintf('Variable \'%s\' is not set', $varname));
        }
    }


    $need_more = (count($braces) > 0) || $open_comment;

    if ($need_more || ";" === $token) {
        $need_semicolon = 0;
    }

    if ($need_return) {
        $eval = "return ".$eval;
    }

    if ($need_more) {
      $_SESSION["partial"] = $eval;
    } else {
      $_SESSION["code"] = $eval;
    }

    return $need_more;
}
?>
