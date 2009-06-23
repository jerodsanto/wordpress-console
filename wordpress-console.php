<?php
/*
Plugin Name: WordPress Console
Plugin URI: http://github.com/sant0sk1/wordpress-console
Description: An interactive console for WordPress developers
Author: Jerod Santo
Author URI: http://jerodsanto.net
Version: 0.1.0
*/

@ob_end_clean();
error_reporting(E_ALL);
set_time_limit(0);

require_once "Shell.php";
require_once "Shell/Extensions/Autoload.php";
require_once "Shell/Extensions/AutoloadDebug.php";
require_once "Shell/Extensions/Colour.php";
require_once "Shell/Extensions/ExecutionTime.php";
require_once "Shell/Extensions/InlineHelp.php";
require_once "Shell/Extensions/VerbosePrint.php";
require_once "Shell/Extensions/LoadScript.php";
require_once dirname(__FILE__) . "/../../../wp-load.php";
    

$__shell = new PHP_Shell();
$__shell_exts = PHP_Shell_Extensions::getInstance();
$__shell_exts->registerExtensions(array(
    "options"        => PHP_Shell_Options::getInstance(), /* the :set command */
    "autoload"       => new PHP_Shell_Extensions_Autoload(),
    "autoload_debug" => new PHP_Shell_Extensions_AutoloadDebug(),
    "colour"         => new PHP_Shell_Extensions_Colour(),
    "exectime"       => new PHP_Shell_Extensions_ExecutionTime(),
    "inlinehelp"     => new PHP_Shell_Extensions_InlineHelp(),
    "verboseprint"   => new PHP_Shell_Extensions_VerbosePrint(),
    "loadscript"     => new PHP_Shell_Extensions_LoadScript(),
));
$__shell_exts->colour->applyColourScheme("dark");

$f = <<<EOF
WordPress Console - 0.1.0%s

>> use '?' for help

EOF;

printf($f, 
    $__shell->hasReadline() ? '' : ', PHP is missing readline support (command history, tab-completion)');
unset($f);

print $__shell_exts->colour->getColour("default");
while($__shell->input()) {
  
  if ($__shell_exts->autoload->isAutoloadEnabled() && !function_exists('__autoload')) {
    function __autoload($classname) {
      global $__shell_exts;

      if ($__shell_exts->autoload_debug->isAutoloadDebug()) {
        print str_repeat(".", $__shell_exts->autoload_debug->incAutoloadDepth())." -> autoloading $classname".PHP_EOL;
      }
      include_once str_replace('_', '/', $classname).'.php';
      if ($__shell_exts->autoload_debug->isAutoloadDebug()) {
        print str_repeat(".", $__shell_exts->autoload_debug->decAutoloadDepth())." <- autoloading $classname".PHP_EOL;
      }
    }
  }

  try {
    $__shell_exts->exectime->startParseTime();
    if ($__shell->parse() == 0) {
      ## we have a full command, execute it

      $__shell_exts->exectime->startExecTime();
      $__shell_retval = eval($__shell->getCode()); 
      if (isset($__shell_retval)) {
        print $__shell_exts->colour->getColour("value");

        if (function_exists("__shell_print_var")) {
          __shell_print_var($__shell_retval, $__shell_exts->verboseprint->isVerbose());
        } else {
          var_export($__shell_retval);
        }
      }
      ## cleanup the variable namespace
      unset($__shell_retval);
      $__shell->resetCode();
    }
  } catch(Exception $__shell_exception) {
    print $__shell_exts->colour->getColour("exception");
    print $__shell_exception->getMessage();

    $__shell->resetCode();

    ## cleanup the variable namespace
    unset($__shell_exception);
  }
  print $__shell_exts->colour->getColour("default");
  $__shell_exts->exectime->stopTime();
  if ($__shell_exts->exectime->isShow()) {
    printf(" (parse: %.4fs, exec: %.4fs)", 
      $__shell_exts->exectime->getParseTime(),
      $__shell_exts->exectime->getExecTime()
      );
  }
}

print $__shell_exts->colour->getColour("reset");

?>