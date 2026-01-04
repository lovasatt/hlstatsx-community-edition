<?php
if ( !defined('IN_UPDATER') )
{
    die('Do not access this file directly.');
}

$dbversion = 93;
$version = "1.12.1";

echo "Executing update script 93 (Weapon Code Correction for CS2 & CS:GO)...<br />";

// 1. Correct weapon code to match Valve logs (sg556) - strictly for CS2 and CS:GO
$db->query("UPDATE IGNORE `hlstats_Weapons` SET `code` = 'sg556' WHERE `code` = 'sg553' AND `game` IN ('cs2', 'csgo')");
$db->query("UPDATE IGNORE `hlstats_Awards` SET `code` = 'sg556' WHERE `code` = 'sg553' AND `game` IN ('cs2', 'csgo')");
$db->query("UPDATE IGNORE `hlstats_Ribbons` SET `awardCode` = 'sg556' WHERE `awardCode` = 'sg553' AND `game` IN ('cs2', 'csgo')");

// 2. Consolidate historical frag data to ensure stats are preserved
$db->query("UPDATE IGNORE `hlstats_Events_Frags` SET `weapon` = 'sg556' WHERE `weapon` = 'sg553'");
$db->query("UPDATE IGNORE `hlstats_Events_Statsme` SET `weapon` = 'sg556' WHERE `weapon` = 'sg553'");
$db->query("UPDATE IGNORE `hlstats_Events_Statsme2` SET `weapon` = 'sg556' WHERE `weapon` = 'sg553'");

// 3. Update system version
$db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
$db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");

echo "<br /><b>Update 93 Technical Summary:</b><br />";
echo "- <b>Target:</b> CS2 and CS:GO weapon 'sg553' remapped to 'sg556' (Valve Standard).<br />";
echo "- <b>Migration:</b> Existing stats for SG 553 have been merged under the correct code.<br />";
echo "<br />Update completed successfully.<br />";
?>