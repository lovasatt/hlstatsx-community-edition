<?php
if ( !defined('IN_UPDATER') )
{
    die('Do not access this file directly.');
}

$dbversion = 94;
$version = "1.12.2";

echo "Executing update script 94 (Generic Hitgroup Support for CS2)...<br />";

// 1. Adding the 'generic' column to the statsme2 table if it doesn't exist
$result = $db->query("SHOW COLUMNS FROM `hlstats_Events_Statsme2` LIKE 'generic'");
if ($result->num_rows == 0) {
    $db->query("ALTER TABLE `hlstats_Events_Statsme2` ADD `generic` INT(6) UNSIGNED NOT NULL DEFAULT '0' AFTER `rightleg` ");
    echo "Added 'generic' column to hlstats_Events_Statsme2 table.<br />";
} else {
    echo "Column 'generic' already exists, skipping...<br />";
}

// 2. Update system version
$db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
$db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");

echo "<br /><b>Update 94 Technical Summary:</b><br />";
echo "- <b>Database:</b> Added 'generic' hitgroup tracking to support CS2 grenade and non-direct damage.<br />";
echo "- <b>Compatibility:</b> Ensures StatsMe2 logs do not drop data when Valve reports hitgroup 0.<br />";
echo "<br />Update completed successfully.<br />";
?>