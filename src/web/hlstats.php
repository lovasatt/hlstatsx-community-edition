<?php
/*
HLstatsX Community Edition - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
http://www.hlxcommunity.com

HLstatsX Community Edition is a continuation of 
ELstatsNEO - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Malte Bayer (steam@neo-soft.org)
http://ovrsized.neo-soft.org/

ELstatsNEO is an very improved & enhanced - so called Ultra-Humongus Edition of HLstatsX
HLstatsX - Real-time player and clan rankings and statistics for Half-Life 2
http://www.hlstatsx.com/
Copyright (C) 2005-2007 Tobias Oetzel (Tobi@hlstatsx.com)

HLstatsX is an enhanced version of HLstats made by Simon Garner
HLstats - Real-time player and clan rankings and statistics for Half-Life
http://sourceforge.net/projects/hlstats/
Copyright (C) 2001  Simon Garner
            
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

For support and installation notes visit http://www.hlxcommunity.com
*/

define('IN_HLSTATS', true);
require('config.php');
$historical_cache=0;
if(defined('HISTORICAL_CACHE'))
{
    $historical_cache=constant('HISTORICAL_CACHE');
}

if($historical_cache==1)
{
    $rawmd5=md5(http_build_query($_REQUEST));
    $dir1=substr($rawmd5,0,1);
    $dir2=substr($rawmd5,1,1);
    $cachetarget=sprintf("cache/%s/%s/%s", $dir1, $dir2, $rawmd5);

    if (!is_dir("cache/$dir1")) @mkdir("cache/$dir1");
    if (!is_dir("cache/$dir1/$dir2")) @mkdir("cache/$dir1/$dir2");

    if(file_exists($cachetarget))
    {
	file_put_contents("cache/cachehit",$cachetarget . "\n", FILE_APPEND);
	echo file_get_contents($cachetarget);
	die;
    }
}

$is_https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
            ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

$protocol = $is_https ? 'https://' : 'http://';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (!empty($_GET['logout']) && $_GET['logout'] == '1') {
    unset($_SESSION['loggedin'], $_SESSION['username'], $_SESSION['authsessionStart']);
    header("Location: " . $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['SCRIPT_NAME'] ?? '/hlstats.php'));
    die;
}

// Several stuff added by Malte Bayer
global $scripttime, $siteurlneo;
$scripttime = microtime(true);

// PHP 8 Fix: Safer URL construction
$script_name = $_SERVER['PHP_SELF'] ?? '';
$last_slash_pos = strrpos($script_name, '/');
if ($last_slash_pos !== false) {
    $path_part = substr($script_name, 0, $last_slash_pos + 1);
} else {
    $path_part = '/';
}
$siteurlneo = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path_part;
$siteurlneo = str_replace('\\','/',$siteurlneo);

// Several Stuff end

foreach ($_SERVER as $key => $entry) {
    // PHP 8 Fix: Only process strings
    if ($key !== 'HTTP_COOKIE' && is_string($entry)) {
	$search_pattern  = array('/<script>/', '/<\/script>/', '/[^A-Za-z0-9.\-\/=:;_?#&~]/');
	$replace_pattern = array('', '', '');
	$entry = preg_replace($search_pattern, $replace_pattern, $entry);

	if ($key == "PHP_SELF") {
            // PHP 8 Fix: Ensure not false/null before checking
            $last_segment = strrchr($entry, '/');
            if ($last_segment !== false) {
		if (($last_segment !== '/hlstats.php') &&
		    ($last_segment !== '/ingame.php') &&
		    ($last_segment !== '/show_graph.php') &&
		    ($last_segment !== '/sig.php') &&
		    ($last_segment !== '/sig2.php') &&
		    ($last_segment !== '/index.php') &&
		    ($last_segment !== '/status.php') &&
		    ($last_segment !== '/top10.php') &&
		    ($last_segment !== '/config.php') &&
		    ($last_segment !== '/') &&
		    ($entry !== '')) {
		    header("Location: " . rtrim($siteurlneo, '/') . "/hlstats.php");
		    exit;
		}
            }
	}
	$_SERVER[$key] = $entry;
    }
}

@header('Content-Type: text/html; charset=utf-8');

// do not report NOTICE warnings or DEPRECATED (legacy codebase compatibility)
@error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED);

////
//// Initialisation
////

define('PAGE', 'HLSTATS');

///
/// Classes
///

// Load required files
require(INCLUDE_PATH . '/class_db.php');
require(INCLUDE_PATH . '/class_table.php');
require(INCLUDE_PATH . '/functions.php');

$db_classname = 'DB_' . DB_TYPE;
if ( class_exists($db_classname) )
{
    $db = new $db_classname(DB_ADDR, DB_USER, DB_PASS, DB_NAME, DB_PCONNECT);
}
else
{
    error('Database class does not exist.  Please check your config.php file for DB_TYPE');
}

$g_options = getOptions();

if (!isset($g_options['scripturl'])) {
    $g_options['scripturl'] = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : getenv('PHP_SELF');
}

////
//// Main
////

// PHP 8 Fix: Null coalescing
$game_input = $_GET['game'] ?? '';
$game = valid_request((string)$game_input, false);

if ($game !== '')
{
    $_SESSION['game'] = $game;
    $realgame = getRealGame($game);
    $_SESSION['realgame'] = $realgame;
}
else
{
    $game = isset($_SESSION['game']) ? (string)$_SESSION['game'] : '';
    $realgame = isset($_SESSION['realgame']) ? (string)$_SESSION['realgame'] : ($game !== '' ? getRealGame($game) : '');
}

$mode = isset($_GET['mode']) ? $_GET['mode'] : '';

$valid_modes = array(
    'players',
    'clans',
    'weapons',
    'roles',
    'rolesinfo',
    'maps',
    'actions',
    'claninfo',
    'playerinfo',
    'weaponinfo',
    'mapinfo',
    'actioninfo',
    'playerhistory',
    'playersessions',
    'playerawards',
    'search',
    'admin',
    'help',
    'bans',
    'servers',
    'chathistory',
    'ranks',
    'rankinfo',
    'ribbons',
    'ribboninfo',
    'chat',
    'globalawards',
    'awards',
    'dailyawardinfo',
    'countryclans',
    'countryclansinfo',
    'teamspeak',
    'ventrilo',
    'discord',
    'updater',
    'profile'
);

// In docker, the updater folder will always be present, to allow
// DB upgrades to be done using this updater. Hence, this code is 
// commented out to allow things to work correctly after the DB upgrade in docker.
// if (file_exists('./updater') && $mode != 'updater')
// {
// 	pageHeader(array('Update Notice'), array('Update Notice' => ''));
// 	echo "<div class=\"warning\">\n" . 
// 	"<span class=\"warning-heading\"><img src=\"".IMAGE_PATH."/warning.gif\" alt=\"Warning\"> Warning:</span><br />\n" .
// 	"<span class=\"warning-text\">The updater folder was detected in your web directory.<br />
// 	To perform a Database Update, please go to <strong><a href=\"{$g_options['scripturl']}?mode=updater\">HLX:CE Database Updater</a></strong> to perform the database update.<br /><br />
// 	<strong>If you have already performed the database update, <strong>you must delete the \"updater\" folder from your web folder.</span>\n</div>";
// 	pageFooter();
// 	die();
// }

if ( !in_array($mode, $valid_modes) )
{
    $mode = 'contents';
}

if ( file_exists(PAGE_PATH . "/$mode.php") )
{
    @include(PAGE_PATH . "/$mode.php");
    pageFooter();
}
else
{
    header('HTTP/1.1 404 File Not Found', false, 404);
    error('Unable to find ' . PAGE_PATH . "/$mode.php");
    pageFooter();
}

?>