<?php
require_once dirname(__FILE__) . "/../../../wp-load.php";

if (!function_exists('json_encode')) {
	function json_encode($value) {
		@require_once('lib/FastJSON.class.php');
		return FastJSON::encode($value);
	}
}

function error($error) {
	exit(json_encode(array('error' => $error)));
}
?>