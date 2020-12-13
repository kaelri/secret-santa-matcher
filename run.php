#!/usr/local/bin/php
<?php

// LOGS
ini_set( 'log_errors', true );
ini_set( 'error_log',  __DIR__ . '/debug.log' );

// CLASS AUTOLOADER
spl_autoload_register(function ($class_name) {
	require_once __DIR__ . '/classes/' . $class_name . '.php';
});

// COMPOSER
// Composer autoloader for third-party dependencies.
require_once __DIR__ . '/vendor/autoload.php';

App::run( __DIR__, $argv );
