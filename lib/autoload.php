<?php
if(!defined('ABSPATH')) die();

spl_autoload_register(function ($class) {
	if(substr($class, 0, 10) != 'searchHero'){
		return false;
	}

	$pieces = explode('\\', $class);
	$file = end($pieces) . '.php';
	if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . $file)) {
		require_once __DIR__ . DIRECTORY_SEPARATOR . $file;
		return true;
	}

	return false;
});
