<?php
function iniSet($name, $default) {
    // PHP 8 Fix: Check strictly for false to allow '0' values
    $env = getenv($name);
    $value = ($env !== false) ? $env : $default;
    ini_set($name, (string)$value);
}

function defineVar($name, $default) {
    // PHP 8 Fix: Check strictly for false to allow '0' values from env
    $env = getenv($name);
    $value = ($env !== false) ? $env : $default;
    
    // Fix string 'false' becoming true for boolean when using settype
    // PHP 8 Safe comparison
    if (gettype($default) === 'boolean' && is_string($value) && strtolower($value) === 'false') {
	$value = false;
    }
    
    settype($value, gettype($default));
    define($name, $value);
}

error_reporting(E_ALL);
iniSet("memory_limit", "32M");
iniSet("max_execution_time", "0");

defineVar('DB_HOST',	'localhost');
defineVar('DB_USER',	'');
defineVar('DB_PASS',	'');
defineVar('DB_NAME',	'');
defineVar('HLXCE_WEB',	'/path/to/where/you/have/your/hlstats/web');
defineVar('HUD_URL',	'http://www.hlxcommunity.com');
defineVar('OUTPUT_SIZE',	'medium');

defineVar('DB_PREFIX',	'hlstats');
defineVar('KILL_LIMIT',	10000);
defineVar('DEBUG', 1);

// No need to change this unless you are on really low disk.
defineVar('CACHE_DIR',	dirname(__FILE__) . '/cache');
"