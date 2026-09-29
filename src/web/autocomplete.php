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
header('X-Content-Type-Options: nosniff');

// Never print PHP errors into the suggestion list
ini_set('display_errors', '0');

// Cookies must not be used as input, so read GET/POST explicitly
$game_input = $_POST['game'] ?? $_GET['game'] ?? '';
$search_input = $_POST['value'] ?? $_GET['value'] ?? $_POST['q'] ?? $_GET['q'] ?? '';

$game = valid_game($game_input);
$search = is_array($search_input) ? '' : trim((string)$search_input);

// Escape LIKE wildcards first, then the SQL string escape doubles the backslashes correctly
$game_escaped = $db->escape($game);
$search_escaped = $db->escape(addcslashes($search, '%_\\'));
 
// Check length in characters, not bytes (accented names)
$search_length = mb_strlen($search, 'UTF-8');
if ($search_length >= 3 && $search_length < 64) {
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

    if ($result) {
        while ($row = $db->fetch_row($result)) {
            // Security Fix: XSS Protection for output
            print "<li class=\"playersearch\">" . htmlspecialchars((string)$row[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
        }
        $db->free_result($result);
    }
}
?>