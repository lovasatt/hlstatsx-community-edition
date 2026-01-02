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

// PHP 8.4 Fix: Safe input handling with explicit casting
$game_input = isset($_GET['game']) ? $_GET['game'] : '';
$search_input = isset($_POST['value']) ? $_POST['value'] : '';

// Ensure valid_request is called correctly (false for string expectation)
$game = function_exists('valid_request') ? valid_request((string)$game_input, false) : (string)$game_input;
// For search queries, we might want to be lenient, but ensuring string type is key
$search = (string)$search_input;

$game_escaped = $db->escape($game);
$search_escaped = $db->escape($search);
 
// Check length
if (strlen($search) >= 3 && strlen($search) < 64) {
    // Building the query
    // Optimizations:
    // 1. Added DISTINCT to avoid duplicate names
    // 2. Added LIMIT to prevent massive result sets crashing the browser
    // 3. Qualified 'game' column to avoid ambiguous column errors
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
            hlstats_Players.game = '$game_escaped' 
            AND hlstats_PlayerNames.name LIKE '$search_escaped%'
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