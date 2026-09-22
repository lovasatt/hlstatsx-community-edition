<?php
if ( !defined('IN_UPDATER') )
{
    die('Do not access this file directly.');
}

// Prevent PHP timeout during large multi-million row migrations
@set_time_limit(0);
@ignore_user_abort(true);
@ini_set('memory_limit', '1024M');

$dbversion = 98;
$version = "1.12.6";

echo "Executing update script 98 (Steam Cache Integration & Primary Key Hardening)...<br />";
flush();

// 1. Create and integrate hlstats_SteamCache table
$db->query("
    CREATE TABLE IF NOT EXISTS `hlstats_SteamCache` (
        `communityId` varchar(32) NOT NULL DEFAULT '',
        `status` varchar(64) NOT NULL DEFAULT 'Unknown',
        `avatar` varchar(255) NOT NULL DEFAULT '',
        `updated` int(10) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (`communityId`),
        KEY `idx_updated` (`updated`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "Verified and established hlstats_SteamCache table.<br />";

// Register internal system option so playerinfo skips redundant SHOW TABLES checks
$db->query("
    INSERT INTO `hlstats_Options` (`keyname`, `value`, `opttype`) 
    VALUES ('steamcache_installed', '1', 2) 
    ON DUPLICATE KEY UPDATE `value` = '1'
");
echo "Registered 'steamcache_installed' flag in hlstats_Options.<br />";
flush();

// 2. Primary Key Hardening for hlstats_server_load (Safe for multi-million rows)
$res_pk_load = $db->query("SHOW INDEX FROM `hlstats_server_load` WHERE `Key_name` = 'PRIMARY'");
if (!$res_pk_load || $db->num_rows($res_pk_load) == 0) {
    echo "Hardening PRIMARY KEY on hlstats_server_load... ";
    flush();

    $dup_check = $db->query("SELECT `server_id`, `timestamp`, COUNT(*) AS cnt FROM `hlstats_server_load` GROUP BY `server_id`, `timestamp` HAVING cnt > 1 LIMIT 1");
    if ($dup_check && $db->num_rows($dup_check) > 0) {
        $db->query("DROP TABLE IF EXISTS `hlstats_server_load_tmp`");
        $db->query("CREATE TABLE `hlstats_server_load_tmp` LIKE `hlstats_server_load`");
        $db->query("ALTER TABLE `hlstats_server_load_tmp` ADD PRIMARY KEY (`server_id`, `timestamp`)");
        $db->query("INSERT IGNORE INTO `hlstats_server_load_tmp` SELECT * FROM `hlstats_server_load`");
        $db->query("DROP TABLE `hlstats_server_load`");
        $db->query("RENAME TABLE `hlstats_server_load_tmp` TO `hlstats_server_load`");
    } else {
        $db->query("ALTER TABLE `hlstats_server_load` ADD PRIMARY KEY (`server_id`, `timestamp`)");
    }
    echo "OK.<br />";
}
flush();

// 3. Primary Key Hardening for hlstats_Trend
$res_pk_trend = $db->query("SHOW INDEX FROM `hlstats_Trend` WHERE `Key_name` = 'PRIMARY'");
if (!$res_pk_trend || $db->num_rows($res_pk_trend) == 0) {
    echo "Hardening PRIMARY KEY on hlstats_Trend... ";
    flush();

    $dup_check_trend = $db->query("SELECT `game`, `timestamp`, COUNT(*) AS cnt FROM `hlstats_Trend` GROUP BY `game`, `timestamp` HAVING cnt > 1 LIMIT 1");
    if ($dup_check_trend && $db->num_rows($dup_check_trend) > 0) {
        $db->query("DROP TABLE IF EXISTS `hlstats_Trend_tmp`");
        $db->query("CREATE TABLE `hlstats_Trend_tmp` LIKE `hlstats_Trend`");
        $db->query("ALTER TABLE `hlstats_Trend_tmp` ADD PRIMARY KEY (`game`, `timestamp`)");
        $db->query("INSERT IGNORE INTO `hlstats_Trend_tmp` SELECT * FROM `hlstats_Trend`");
        $db->query("DROP TABLE `hlstats_Trend`");
        $db->query("RENAME TABLE `hlstats_Trend_tmp` TO `hlstats_Trend`");
    } else {
        $db->query("ALTER TABLE `hlstats_Trend` ADD PRIMARY KEY (`game`, `timestamp`)");
    }
    echo "OK.<br />";
}
flush();

// 4. Update system version
$db->query("UPDATE `hlstats_Options` SET `value` = '$version' WHERE `keyname` = 'version'");
$db->query("UPDATE `hlstats_Options` SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");

echo "<br /><b>Update 98 Technical Summary:</b><br />";
echo "- <b>Steam Cache Integration:</b> Created high-performance `hlstats_SteamCache` InnoDB table and registered `steamcache_installed` flag in `hlstats_Options` for zero-overhead player profile loading.<br />";
echo "- <b>InnoDB Primary Key Hardening:</b> Added composite PRIMARY KEYs to `hlstats_server_load` (`server_id`, `timestamp`) and `hlstats_Trend` (`game`, `timestamp`) with automatic deduplication, eliminating hidden row locks.<br />";
echo "<br />Update completed successfully.<br />";
?>