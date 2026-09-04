<?php
define('IN_HLSTATS', true);

// Load required files
require('config.php');
require(INCLUDE_PATH . '/class_db.php');
require(INCLUDE_PATH . '/functions.php');

$db_classname = 'DB_' . DB_TYPE;
if (class_exists($db_classname)) {
    $db = new $db_classname(DB_ADDR, DB_USER, DB_PASS, DB_NAME, DB_PCONNECT);
} else {
    error('Database class does not exist.  Please check your config.php file for DB_TYPE');
}

header('Content-Type: text/html; charset=utf-8');

// PHP 8.4 Fix: Safe input handling (supports both POST and GET)
$game_input = $_REQUEST['game'] ?? '';
$search_input = $_POST['value'] ?? $_GET['value'] ?? $_POST['q'] ?? $_GET['q'] ?? '';

$game = function_exists('valid_request') ? valid_request((string)$game_input, false) : (string)$game_input;
$search = trim((string)$search_input);

$game_escaped = $db->escape($game);
$search_escaped = $db->escape($search);
 
// Check length
if (strlen($search) >= 3 && strlen($search) < 64) {
    $game_clause = ($game !== '') ? "hlstats_Players.game = '$game_escaped' AND " : "";

    $sql = "
        SELECT DISTINCT
            hlstats_PlayerNames.name
        FROM
            hlstats_PlayerNames
        INNER JOIN
            hlstats_Players
        ON
            hlstats_PlayerNames.playerId = hlstats_Players.playerId
        WHERE
            $game_clause hlstats_PlayerNames.name LIKE '$search_escaped%'
        ORDER BY
            LENGTH(hlstats_PlayerNames.name), hlstats_PlayerNames.name
        LIMIT 15
    ";
    
    $result = $db->query($sql);

    while($row = $db->fetch_row($result)) {
        // Security Fix: XSS Protection for output
	print "<li class=\"playersearch\">" . htmlspecialchars((string)$row[0], ENT_QUOTES, 'UTF-8') . "</li>\n";
    }
}
?>