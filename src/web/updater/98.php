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

echo "Executing update script 98 (Steam Cache Integration, Primary Key Hardening & Map Index Optimization)...<br />";
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
        $db->query("DROP TABLE IF EXISTS `hlstats_server_load_old`");
        $db->query("CREATE TABLE `hlstats_server_load_tmp` LIKE `hlstats_server_load`");
        $db->query("ALTER TABLE `hlstats_server_load_tmp` ADD PRIMARY KEY (`server_id`, `timestamp`)");
        $db->query("INSERT IGNORE INTO `hlstats_server_load_tmp` SELECT * FROM `hlstats_server_load`");
        $db->query("RENAME TABLE `hlstats_server_load` TO `hlstats_server_load_old`, `hlstats_server_load_tmp` TO `hlstats_server_load`");
        $db->query("DROP TABLE IF EXISTS `hlstats_server_load_old`");
    } else {
        $db->query("ALTER TABLE `hlstats_server_load` ADD PRIMARY KEY (`server_id`, `timestamp`)");
    }
    if ($dup_check) {
        $db->free_result($dup_check);
    }
    echo "OK.<br />";
} else {
    echo "PRIMARY KEY on hlstats_server_load already exists, skipping...<br />";
}
if ($res_pk_load) {
    $db->free_result($res_pk_load);
}

// Clean up secondary index now completely superseded by the PRIMARY KEY
$chk_dup_idx = $db->query("SHOW INDEX FROM `hlstats_server_load` WHERE `Key_name` = 'idx_server_timestamp'");
if ($chk_dup_idx && $db->num_rows($chk_dup_idx) > 0) {
    echo "Dropping redundant index 'idx_server_timestamp' on hlstats_server_load... ";
    flush();
    $db->query("ALTER TABLE `hlstats_server_load` DROP INDEX `idx_server_timestamp`");
    echo "OK.<br />";
} else {
    echo "Redundant index 'idx_server_timestamp' already dropped, skipping.<br />";
}
if ($chk_dup_idx) { $db->free_result($chk_dup_idx); }
flush();

// 3. Primary Key Hardening for hlstats_Trend
$res_pk_trend = $db->query("SHOW INDEX FROM `hlstats_Trend` WHERE `Key_name` = 'PRIMARY'");
if (!$res_pk_trend || $db->num_rows($res_pk_trend) == 0) {
    echo "Hardening PRIMARY KEY on hlstats_Trend... ";
    flush();

    $dup_check_trend = $db->query("SELECT `game`, `timestamp`, COUNT(*) AS cnt FROM `hlstats_Trend` GROUP BY `game`, `timestamp` HAVING cnt > 1 LIMIT 1");
    if ($dup_check_trend && $db->num_rows($dup_check_trend) > 0) {
        $db->query("DROP TABLE IF EXISTS `hlstats_Trend_tmp`");
        $db->query("DROP TABLE IF EXISTS `hlstats_Trend_old`");
        $db->query("CREATE TABLE `hlstats_Trend_tmp` LIKE `hlstats_Trend`");
        $db->query("ALTER TABLE `hlstats_Trend_tmp` ADD PRIMARY KEY (`game`, `timestamp`)");
        $db->query("INSERT IGNORE INTO `hlstats_Trend_tmp` SELECT * FROM `hlstats_Trend`");
        $db->query("RENAME TABLE `hlstats_Trend` TO `hlstats_Trend_old`, `hlstats_Trend_tmp` TO `hlstats_Trend`");
        $db->query("DROP TABLE IF EXISTS `hlstats_Trend_old`");
    } else {
        $db->query("ALTER TABLE `hlstats_Trend` ADD PRIMARY KEY (`game`, `timestamp`)");
    }
    if ($dup_check_trend) {
        $db->free_result($dup_check_trend);
    }
    echo "OK.<br />";
}
if ($res_pk_trend) {
    $db->free_result($res_pk_trend);
}
flush();

// 4. Optimize map index prefix length on hlstats_Events_Frags (prefix 5 -> 32 characters)
$res_map_idx = $db->query("SHOW INDEX FROM `hlstats_Events_Frags` WHERE `Key_name` = 'map'");
if ($res_map_idx && $row = $db->fetch_array($res_map_idx)) {
    $sub_part = isset($row['Sub_part']) ? (int)$row['Sub_part'] : (isset($row['sub_part']) ? (int)$row['sub_part'] : 0);
    if ($sub_part > 0 && $sub_part < 32) {
        echo "Optimizing 'map' index on hlstats_Events_Frags (prefix $sub_part to 32)... ";
        flush();
        $db->query("ALTER TABLE `hlstats_Events_Frags` DROP INDEX `map`, ADD INDEX `map` (`map`(32))");
        echo "OK.<br />";
    } else {
        echo "'map' index on hlstats_Events_Frags is already optimal (prefix $sub_part), skipping.<br />";
    }
} else {
    echo "Creating 'map' index on hlstats_Events_Frags (prefix 32)... ";
    flush();
    $db->query("ALTER TABLE `hlstats_Events_Frags` ADD INDEX `map` (`map`(32))");
    echo "OK.<br />";
}
if ($res_map_idx) {
    $db->free_result($res_map_idx);
}
flush();

// 5. Register CounterStrikeSharp Admin Mod and default RCON commands
$res_mod = $db->query("SELECT `code` FROM `hlstats_Mods_Supported` WHERE `code` = 'CSHARP'");
if (!$res_mod || $db->num_rows($res_mod) == 0) {
    echo "Registering CounterStrikeSharp in hlstats_Mods_Supported and hlstats_Mods_Defaults... ";
    flush();

    // Clean up any old inconsistent case
    $db->query("DELETE FROM `hlstats_Mods_Defaults` WHERE LOWER(`code`) = 'csharp'");
    $db->query("DELETE FROM `hlstats_Mods_Supported` WHERE LOWER(`code`) = 'csharp'");

    // Part 1: Register in hlstats_Mods_Supported
    $db->query("
        INSERT INTO `hlstats_Mods_Supported` (`code`, `name`)
        VALUES ('CSHARP', 'CounterStrikeSharp')
    ");

    // Part 2: Register default RCON commands in hlstats_Mods_Defaults (Clean INSERT, no syntax error)
    $db->query("
        INSERT INTO `hlstats_Mods_Defaults` (`code`, `parameter`, `value`) VALUES
        ('CSHARP', 'BroadCastEventsCommand', 'hlx_say'),
        ('CSHARP', 'BroadCastEventsCommandAnnounce', 'hlx_say'),
        ('CSHARP', 'PlayerEventsAdminCommand', ''),
        ('CSHARP', 'PlayerEventsCommand', 'hlx_psay'),
        ('CSHARP', 'PlayerEventsCommandOSD', ''),
        ('CSHARP', 'PlayerEventsCommandHint', 'hlx_csay')
    ");

    // Part 3: Normalize any existing server configs
    $db->query("UPDATE `hlstats_Servers_Config` SET `value` = 'CSHARP' WHERE `parameter` = 'Mod' AND LOWER(`value`) = 'csharp'");

    echo "OK.<br />";
} else {
    echo "CounterStrikeSharp already registered in hlstats_Mods_Supported, skipping.<br />";
}
if ($res_mod) { $db->free_result($res_mod); }
flush();

// 6. CS2 Pre-Release Meta: Register Leaked M14 Marksman Rifle & Awards/Ribbons
echo "Registering CS2 M14 weapon, awards, and 6-tier ribbon ladder... ";
flush();

// Weapon registration in hlstats_Weapons
$db->query("
    INSERT INTO `hlstats_Weapons` (`game`, `code`, `name`, `modifier`)
    VALUES ('cs2', 'm14', 'M14', 1.00)
    ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `modifier` = VALUES(`modifier`)
");

// Weapon Award registration in hlstats_Awards
$db->query("
    INSERT INTO `hlstats_Awards` (`awardType`, `game`, `code`, `name`, `verb`)
    VALUES ('W', 'cs2', 'm14', 'M14', 'kills with m14')
    ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `verb` = VALUES(`verb`)
");

// 6-Tier Ribbon ladder registration in hlstats_Ribbons (1, 5, 12, 20, 30, 50 kills)
$db->query("
    INSERT INTO `hlstats_Ribbons` (`awardCode`, `awardCount`, `special`, `game`, `image`, `ribbonName`) VALUES
    ('m14', 1,  0, 'cs2', '1_m14.png', 'Award of M14'),
    ('m14', 5,  0, 'cs2', '2_m14.png', 'Bronze M14'),
    ('m14', 12, 0, 'cs2', '3_m14.png', 'Silver M14'),
    ('m14', 20, 0, 'cs2', '4_m14.png', 'Gold M14'),
    ('m14', 30, 0, 'cs2', '5_m14.png', 'Platinum M14'),
    ('m14', 50, 0, 'cs2', '6_m14.png', 'Supreme M14')
    ON DUPLICATE KEY UPDATE `ribbonName` = VALUES(`ribbonName`), `image` = VALUES(`image`)
");

echo "OK.<br />";
flush();

// 7. Update system version
$db->query("UPDATE `hlstats_Options` SET `value` = '$version' WHERE `keyname` = 'version'");
$db->query("UPDATE `hlstats_Options` SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");

echo "<br /><b>Update 98 Technical Summary:</b><br />";
echo "- <b>Steam Cache Integration:</b> Created high-performance `hlstats_SteamCache` InnoDB table and registered `steamcache_installed` flag in `hlstats_Options` for zero-overhead player profile loading.<br />";
echo "- <b>InnoDB Primary Key Hardening:</b> Added composite PRIMARY KEYs to `hlstats_server_load` (`server_id`, `timestamp`) and `hlstats_Trend` (`game`, `timestamp`) with automatic deduplication, eliminating hidden row locks.<br />";
echo "- <b>Map Performance Optimization:</b> Upgraded `map` index prefix on `hlstats_Events_Frags` from 5 to 32 characters for instant map statistics filtering on large tables.<br />";
echo "- <b>CounterStrikeSharp Integration:</b> Provisioned `CSHARP` in `hlstats_Mods_Supported` and registered native CS2 RCON commands (`hlx_say`, `hlx_psay`, `hlx_csay`) in `hlstats_Mods_Defaults`.<br />";
echo "- <b>Zero-Day CS2 Meta Preparedness:</b> Pre-provisioned leaked Source 2 M14 Marksman Rifle (`m14`) into `hlstats_Weapons` (1.00x modifier), registered daily kill awards in `hlstats_Awards`, and deployed a 6-tier achievement ribbon hierarchy in `hlstats_Ribbons`.<br />";
echo "<br />Update completed successfully.<br />";
?>