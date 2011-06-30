<?php
require('common.php');

if (isset($_POST['partial'])){
  $secret = get_option('wordpress-console-secret');
  if (!$secret){
    return ;
  }

  if (!isset($_POST['signature']) || !$_POST['signature']){
    return ;
  }

  $partial = stripslashes($_POST['partial']);
  if (hash_hmac('sha1', $partial, $secret) != $_POST['signature']){
    return ;
  }

  $show_parameter = (isset($_POST['show_parameter']) && $_POST['show_parameter'] == 1) ? true : false;

  if (!preg_match('#(((?:\$)?(?:[0-9a-z_-])*)(?:(?:\[(?:[^\]]+)\]->)|(?:>|::))?)$#i', $partial, $m)){
    die(json_encode(false));
  }

  $candidates = complete($m[0], $show_parameter);
  sort($candidates);
  die(json_encode((array)$candidates));
}

else{
  error('Error initializing session.');
}

// returns array of possible matches
// returns array of possible matches
function complete($string, $show_parameter){
  /**
   * parse the line-buffer backwards to see if we have a
   * - constant
   * - function
   * - variable
   */

  $m = array();

  if (preg_match('#\$([A-Za-z0-9_]+)->#', $string, $a)){
    /* check for $o->... */
    $name = $a[1];

    if (isset($GLOBALS[$name]) && is_object($GLOBALS[$name])){
      $class_info = get_class_structure($GLOBALS[$name]);

      if (isset($class_info['methods']) && !empty($class_info['methods'])){
        foreach($class_info['methods'] as $method_name => $method_info){
          $m[] = class_method_info($GLOBALS[$name], $method_name, $show_parameter);
        }
      }

      if (isset($class_info['properties']) && !empty($class_info['properties'])){
        $m = array_merge($m, class_property_info($GLOBALS[$name]));
      }

      return $m;
    }
  }
  else if (preg_match('#\$([A-Za-z0-9_]+)\[([^\]]+)\]->#', $string, $a)){
    /* check for $o[...]->... */
    $name = $a[1];

    if (isset($GLOBALS[$name]) && is_array($GLOBALS[$name]) && isset($GLOBALS[$name][$a[2]])){
      $class_info = get_class_structure($GLOBALS[$name][$a[2]]);

      if (isset($class_info['methods']) && !empty($class_info['methods'])){
        foreach($class_info['methods'] as $method_name => $method_info){
          $m[] = class_method_info($GLOBALS[$name][$a[2]], $method_name, $show_parameter);
        }
      }

      if (isset($class_info['properties']) && !empty($class_info['properties'])){
        $m = array_merge($m, class_property_info($GLOBALS[$name][$a[2]]));
      }
      return $m;
    }
  }
  else if (preg_match('#([A-Za-z0-9_]+)::#', $string, $a)){
    /* check for Class:: */
    $name = $a[1];

    if (class_exists($name, false)){
      $class_info = get_class_structure($name);

      if (isset($class_info['methods']) && !empty($class_info['methods'])){
        foreach($class_info['methods'] as $method_name => $method_info){
          $m[] = class_method_info($name, $method_name, $show_parameter);
        }
      }

      if (isset($class_info['properties']) && !empty($class_info['properties'])){

        $m = array_merge($m, class_property_info($name));
      }

      return $m;
    }
  }
  else if (preg_match('#\$([a-zA-Z]?[a-zA-Z0-9_]*)$#', $string)){
    $m = array_keys($GLOBALS);

    return $m;
  }
  else if (preg_match('#new #', $string)){
    $c = get_declared_classes();

    foreach($c as $name){
      $class_info = get_class_structure($name);

      if (isset($class_info['methods']) && !empty($class_info['methods'])){
        foreach($class_info['methods'] as $method_name => $method_info){
          $m[] = class_method_info($name, $method_name, $show_parameter);
        }
      }
    }

    if (isset($class_info['properties']) && !empty($class_info['properties'])){
      $m = array_merge($m, class_property_info($name));
    }

    return $m;
  }
  else if (preg_match('#^:set #', $string)){
    foreach(PHP_Shell_Options::getInstance()->getOptions()as $v){
      $m[] = $v;
    }

    return $m;
  }

  $f = get_defined_functions();

  foreach($f['internal'] as $v){
    if (preg_match("/^{$string}/", $v)){
      $m[] = function_info($v, true);
    }
  }

  foreach($f['user'] as $v){
    if (preg_match("/^{$string}/", $v)){
      $m[] = function_info($v, true);
    }
  }

  $c = get_declared_classes();

  foreach($c as $v){
    if (preg_match("/^{$string}/", $v)){
      $m[] = $v.'::';
    }
    //$m[] = class_info($v, $show_parameter);
  }

  $c = get_defined_constants();

  foreach($c as $k => $v){
    if (preg_match("/^{$string}/", $v)){
      $m[] = $v;
    }
  }

  /* taken from http://de3.php.net/manual/en/reserved.php */
  /* taken from http://de3.php.net/manual/en/reserved.php */
  $reserved[] = 'abstract';
  $reserved[] = 'and';
  $reserved[] = ($show_parameter) ? 'array([ mixed $... ])' : 'array(';
  $reserved[] = 'as';
  $reserved[] = 'break';
  $reserved[] = 'case';
  $reserved[] = 'catch';
  $reserved[] = 'class';
  $reserved[] = 'const';
  $reserved[] = 'continue';
  # $reserved[] = 'declare';
  $reserved[] = 'default';
  $reserved[] = ($show_parameter) ? 'die([$status])' : 'die(';
  $reserved[] = 'do';
  $reserved[] = ($show_parameter) ? 'echo(string $arg1 [, string $... ])' : 'echo(';
  $reserved[] = 'else';
  $reserved[] = 'elseif';
  $reserved[] = ($show_parameter) ? 'empty(mixed $var)' : 'empty(';
  # $reserved[] = 'enddeclare';
  $reserved[] = ($show_parameter) ? 'eval(string $code_str)' : 'eval(';
  $reserved[] = 'exception';
  $reserved[] = 'extends';
  $reserved[] = ($show_parameter) ? 'exit([$status])' : 'exit(';
  $reserved[] = 'extends';
  $reserved[] = 'final';
  $reserved[] = ($show_parameter) ? 'for (expr1; expr2; expr3)' : 'for (';
  $reserved[] = ($show_parameter) ? 'foreach (array_expression as $key => $value | array_expression as $value)' : 'foreach (';
  $reserved[] = 'function';
  $reserved[] = 'global';
  $reserved[] = 'if';
  $reserved[] = 'implements';
  $reserved[] = 'include "';
  $reserved[] = 'include_once "';
  $reserved[] = 'interface';
  $reserved[] = ($show_parameter) ? 'isset(mixed $var [, mixed $var [, $... ]])' : 'isset(';
  $reserved[] = ($show_parameter) ? 'list(mixed $varname [, mixed $... ])' : 'list(';
  $reserved[] = 'new';
  $reserved[] = 'or';
  $reserved[] = ($show_parameter) ? 'print(string $arg)' : 'print(';
  $reserved[] = 'private';
  $reserved[] = 'protected';
  $reserved[] = 'public';
  $reserved[] = 'require "';
  $reserved[] = 'require_once "';
  $reserved[] = 'return';
  $reserved[] = 'static';
  $reserved[] = ($show_parameter) ? 'switch (expr)' : 'switch (';
  $reserved[] = 'throw';
  $reserved[] = 'try';
  $reserved[] = ($show_parameter) ? 'unset(mixed $var [, mixed $var [, mixed $... ]])' : 'unset (';
  # $reserved[] = 'use';
  $reserved[] = 'var';
  $reserved[] = 'while';
  $reserved[] = 'xor';
  $reserved[] = '__FILE__';
  $reserved[] = '__FUNCTION__';
  $reserved[] = '__CLASS__';
  $reserved[] = '__LINE__';
  $reserved[] = '__METHOD__';


  foreach($reserved as $v){
    if (preg_match("/^{$string}/", $v)){
      $m[] = $v;
    }
  }

  return $m;
}

/*
This function will return an array that describe the class structure
array(
'name' => 'function_name',
'parameters' => array(
'$parameter1' => array(
'optional' => bool,
'default_value' => ''
),...
)
)
 */
function get_function_structure($function_name){
  // variable to store class information
  $function_info;

  $reflected_function = new ReflectionFunction($function_name);
  // function name
  $function_info['name'] = $reflected_function->getName();

  // loop through all the class method
  foreach($reflected_function->getParameters()as $function_parameter){
    // method name, add &, if it is pass by reference
    $parameter_name = ($function_parameter->isPassedByReference()) ? '&' : '';
    $parameter_name .= '$'.$function_parameter->getName();

    $function_info['parameters'][$parameter_name] = array('optional' => $function_parameter->isOptional(),
    // if the function is not defined by the user, the reflection class cannot determine the default value for it's parameters
    'default_value' => ($function_parameter->isOptional() &&
      $reflected_function->isUserDefined()) ? $function_parameter
      ->getDefaultValue(): ''
    );
  }
  return $function_info;
}

// This function will return a nicely formatted string describing the function
function function_info($function_name, $show_parameter = true){
  // load the function structure
  $function_info = get_function_structure($function_name);

  $string = $function_info['name'];

  if ($show_parameter){
    $string .= '(';

    $params;
    if (isset($function_info['parameters']) && !empty($function_info['parameters'])){
      foreach($function_info['parameters'] as $parameter_name => $parameter_info){
        if ($parameter_info['optional']){
          $params[] = '['.$parameter_name.' = \''.$parameter_info['default_value'].'\']';
        }
        else{
          $params[] = $parameter_name;
        }
      }

      $string .= (count($params)) ? implode(', ', $params): '';
    }

    $string .= ')';
  }
  else{
    $string .= '(';
  }

  return $string;
}

function get_class_structure($class_name){
  // variable to store class information
  $class_info;

  $reflected_class = new ReflectionClass($class_name);
  // class name
  $class_info['name'] = $reflected_class->getName();

  // loop through and store add parents
  $reflected_parent_class = $reflected_class->getParentClass();
  if (isset($reflected_parent_class) && !empty($reflected_parent_class)){
    foreach($reflected_parent_class as $class_parent){
      $class_info['parents'][] = $class_parent;
    }
  }

  foreach($reflected_class->getProperties()as $property){
    // property name and it's modifier
    $class_info['properties'][$property->name]['modifiers'] = Reflection::getModifierNames($property->getModifiers());
  }

  foreach($reflected_class->getDefaultProperties()as $property_name => $default_value){
    // property name and it's modifier
    // property default value
    $class_info['properties'][$property_name]['default_value'] = $default_value;
  }

  // loop through all the class method
  foreach($reflected_class->getMethods()as $method){
    // method name and it's modifier
    $class_info['methods'][$method->name]['modifiers'] = Reflection::getModifierNames($method->getModifiers());


    // loop through each of the method parameter
    foreach($method->getParameters()as $method_parameter){
      // parameter name, add &, if it is pass by reference
      $parameter_name = ($method_parameter->isPassedByReference()) ? '&' : '';
      $parameter_name .= '$'.$method_parameter->getName();

      $class_info['methods'][$method->name]['parameters'][$parameter_name] = array('optional' => $method_parameter->isOptional(),
      // if the function is not defined by the user, the reflection class cannot determine the default value for it's parameters
      'default_value' => ($method_parameter->isOptional() && $method
        ->isUserDefined()) ? $method_parameter->getDefaultValue(): ''
      );
    }
  }
  return $class_info;
}


function class_info($class_name, $show_parameter = true){
  // load the class structure
  $class_info = get_class_structure($class_name);

  $string = "\n".$class_info['name'];

  if ($show_parameter){
    $string .= '::';

    if (isset($class_info['parents']) && !empty($class_info['parents'])){
      $string .= 'inherit from '.''.implode(', ', $class_info['parents']);
    }

    $string .= "\n";

    if (isset($class_info['methods']) && !empty($class_info['methods'])){
      // loop through all the methods
      foreach($class_info['methods'] as $method_name => $method_info){
        $string .= implode(' ', $method_info['modifiers']).' '.$method_name.'(';

        // loop through all the method parameters
        $params;

        if (isset($method_info['parameters']) && !empty($method_info['parameters'])){
          foreach($method_info['parameters'] as $parameter_name => $parameter_info){
            if ($parameter_info['optional']){
              $params[] = '['.$parameter_name.' = \''.$parameter_info['default_value'].'\']';
            }
            else{
              $params[] = $parameter_name;
            }
          }
          $string .= (count($params)) ? implode(', ', $params): '';
        }


        $string .= ')'."\n";

      }

    }
  }

  return $string .= "\n".'END OF CLASS '.$class_info['name']."\n\n\n";
}

function class_method_info($class_name, $method_name, $show_parameter){
  // load the class structure
  $class_info = get_class_structure($class_name);

  $string;

  if (isset($class_info['methods']) && !empty($class_info['methods'])){

    $method_info = $class_info['methods'][$method_name];

    $string = implode(' ', $method_info['modifiers']).' '.$method_name.'(';

    if ($show_parameter){

      // loop through all the method parameters
      $params;

      if (isset($method_info['parameters']) && !empty($method_info['parameters'])){
        foreach($method_info['parameters'] as $parameter_name => $parameter_info){
          if ($parameter_info['optional']){
            $params[] = '['.$parameter_name.' = \''.$parameter_info['default_value'].'\']';
          }
          else{
            $params[] = $parameter_name;
          }
        }
        $string .= (count($params)) ? implode(', ', $params): '';
      }
    }
    $string .= ')';
  }
  return $string;
}

function class_property_info($class_name){
  // load the class structure
  $properties_info = get_class_structure($class_name);

  $a = array();

  if (isset($properties_info['properties']) && !empty($properties_info['properties'])){
    foreach($properties_info['properties'] as $property_name => $property_info){
      $s = implode(' ', $properties_info['properties'][$property_name]['modifiers']).' '.$property_name;
      $s .= (isset($properties_info['properties'][$property_name]['default_value'])) ? ' = \''.$properties_info['properties'][$property_name]['default_value'].'\'': '';
      $a[] = $s;
    }
  }
  return $a;
}

?>