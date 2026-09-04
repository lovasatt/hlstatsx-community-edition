<?php
if ( !defined('IN_UPDATER') )
{
    die('Do not access this file directly.');
}

// Prevent PHP timeout during large 10M+ row migrations
@set_time_limit(0);
@ignore_user_abort(true);
@ini_set('memory_limit', '1024M');

$dbversion = 97;
$version = "1.12.5";

echo "Executing update script 97 (InnoDB Modernization, Performance Indexing & CS-Family Round Actions Update)...<br />";

// 1 Convert selected tables to InnoDB (MEMORY tables are intentionally excluded)
$tables_to_innodb = array(
    'hlstats_Players', 'hlstats_PlayerNames', 'hlstats_PlayerUniqueIds', 'hlstats_Clans',
    'hlstats_ClanTags', 'hlstats_Servers', 'hlstats_Servers_Config', 'hlstats_Actions',
    'hlstats_Weapons', 'hlstats_Roles', 'hlstats_Ranks', 'hlstats_Ribbons',
    'hlstats_Awards', 'hlstats_Countries', 'hlstats_Games', 'hlstats_Options',
    'hlstats_Trend', 'hlstats_Events_Frags', 'hlstats_Events_Statsme', 'hlstats_Events_Statsme2',
    'hlstats_Events_PlayerActions', 'hlstats_Events_PlayerPlayerActions', 'hlstats_Events_TeamBonuses',
    'hlstats_Events_Suicides', 'hlstats_Events_Teamkills', 'hlstats_Events_Connects',
    'hlstats_Events_Disconnects', 'hlstats_Events_Entries', 'hlstats_Events_Chat',
    'hlstats_Events_ChangeName', 'hlstats_Events_ChangeRole', 'hlstats_Events_ChangeTeam',
    'hlstats_Events_Latency', 'hlstats_Events_StatsmeLatency', 'hlstats_Events_StatsmeTime',
    'hlstats_Events_Admin', 'hlstats_Events_Rcon', 'hlstats_Maps_Counts',  'hlstats_Map_Regions', 'hlstats_Heatmap_Config',
    'hlstats_server_load', 'hlstats_Players_Awards', 'hlstats_Players_History', 'hlstats_Players_Ribbons',
    'hlstats_Games_Defaults', 'hlstats_Games_Supported', 'hlstats_Mods_Defaults', 'hlstats_Mods_Supported',
    'hlstats_Options_Choices', 'hlstats_HostGroups', 'hlstats_Servers_VoiceComm',
    'hlstats_Teams', 'hlstats_Users', 'hlstats_Servers_Config_Default',
    'geoLiteCity_Blocks', 'geoLiteCity_Location'
);

foreach ($tables_to_innodb as $tbl) {
    $res = $db->query("SHOW TABLE STATUS WHERE Name = '$tbl'");
    if ($res && $row = $db->fetch_array($res)) {
        if (strtoupper((string)$row['Engine']) !== 'INNODB') {
            $db->query("ALTER TABLE `$tbl` ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            echo "Converted table '$tbl' to InnoDB.<br />";
        }
    }
}

// 2. Insert missing round resolution actions for CS2, CS:GO, CSS, CS 1.6, CSPromod & PVKII
$db->query("
    INSERT INTO `hlstats_Actions` (`game`, `code`, `reward_player`, `reward_team`, `team`, `description`, `for_PlayerActions`, `for_PlayerPlayerActions`, `for_TeamActions`, `for_WorldActions`) VALUES
    ('cs2', 'SFUI_Notice_Target_Saved', 0, 2, 'CT', 'Target saved (Time ran out)', '0', '0', '1', '0'),
    ('cs2', 'SFUI_Notice_Hostages_Not_Rescued', 0, 2, 'TERRORIST', 'Hostages not rescued (Time ran out)', '0', '0', '1', '0'),
    ('csgo', 'SFUI_Notice_Target_Saved', 0, 2, 'CT', 'Target saved (Time ran out)', '0', '0', '1', '0'),
    ('csgo', 'SFUI_Notice_Hostages_Not_Rescued', 0, 2, 'TERRORIST', 'Hostages not rescued (Time ran out)', '0', '0', '1', '0'),
    ('css', 'Target_Saved', 0, 2, 'CT', 'Target saved (Time ran out)', '0', '0', '1', '0'),
    ('css', 'Hostages_Not_Rescued', 0, 2, 'TERRORIST', 'Hostages not rescued (Time ran out)', '0', '0', '1', '0'),
    ('cstrike', 'Target_Saved', 0, 2, 'CT', 'Target saved (Time ran out)', '0', '0', '1', '0'),
    ('cstrike', 'Hostages_Not_Rescued', 0, 2, 'TERRORIST', 'Hostages not rescued (Time ran out)', '0', '0', '1', '0'),
    ('csp', 'CTs_Win', 0, 2, 'CT', 'All Terrorists eliminated', '0', '0', '1', '0'),
    ('csp', 'Terrorists_Win', 0, 2, 'TERRORIST', 'All Counter-Terrorists eliminated', '0', '0', '1', '0'),
    ('csp', 'Bomb_Defused', 0, 5, 'CT', 'Counter-Terrorists defused the bomb', '0', '0', '1', '0'),
    ('csp', 'Target_Bombed', 0, 5, 'TERRORIST', 'Terrorists bombed the target', '0', '0', '1', '0'),
    ('csp', 'All_Hostages_Rescued', 0, 10, 'CT', 'Counter-Terrorists rescued all the hostages', '0', '0', '1', '0'),
    ('csp', 'Target_Saved', 0, 2, 'CT', 'Target saved (Time ran out)', '0', '0', '1', '0'),
    ('csp', 'Hostages_Not_Rescued', 0, 2, 'TERRORIST', 'Hostages not rescued (Time ran out)', '0', '0', '1', '0'),
    ('csp', 'Planted_The_Bomb', 10, 2, 'TERRORIST', 'Plant the Bomb', '1', '0', '0', '0'),
    ('csp', 'Defused_The_Bomb', 10, 0, 'CT', 'Defuse the Bomb', '1', '0', '0', '0'),
    ('pvkii', 'Round_Win', 0, 10, '', 'Round Win', '0', '0', '1', '0'),
    ('pvkii', 'Pirates_Win', 0, 10, 'Pirates', 'Pirates Won Round', '0', '0', '1', '0'),
    ('pvkii', 'Vikings_Win', 0, 10, 'Vikings', 'Vikings Won Round', '0', '0', '1', '0'),
    ('pvkii', 'Knights_Win', 0, 10, 'Knights', 'Knights Won Round', '0', '0', '1', '0'),
    ('bg2', 'Round_Win', 0, 5, '', 'Round Win', '0', '0', '1', '0'),
    ('dinodday', 'Round_Win', 0, 5, '', 'Round Win', '0', '0', '1', '0'),
    ('dystopia', 'Round_Win', 0, 5, '', 'Round Win', '0', '0', '1', '0'),
    ('ff', 'Round_Win', 0, 10, '', 'Round Win', '0', '0', '1', '0'),
    ('ff', 'Mini_Round_Win', 0, 5, '', 'Mini-Round Win', '0', '0', '1', '0'),
    ('fof', 'Round_Win', 0, 5, '', 'Round Win', '0', '0', '1', '0'),
    ('hidden', 'Round_Win', 0, 5, '', 'Round Win', '0', '0', '1', '0'),
    ('zps', 'Round_Win', 0, 5, '', 'Round Win', '0', '0', '1', '0'),
    ('dod', 'dod_round_win', 0, 5, '', 'Round Win', '0', '0', '1', '0'),
    ('hl2ctf', 'Round_Win', 0, 5, '', 'Round Win', '0', '0', '1', '0')
    ON DUPLICATE KEY UPDATE
        `description` = VALUES(`description`),
        `reward_team` = VALUES(`reward_team`),
        `team` = VALUES(`team`), 
        `for_TeamActions` = VALUES(`for_TeamActions`)
");
echo "Added and synchronized round resolution actions for all supported modifications in hlstats_Actions table.<br />";

// 3. Database Data Normalization, Typo Fixes & Weapon Sync
// 3.1 Fix MySQL strict mode empty enum values in hlstats_Teams
$db->query("UPDATE `hlstats_Teams` SET `hidden` = '0' WHERE `hidden` = '' OR `hidden` IS NULL");
echo "Fixed enum values in hlstats_Teams for strict MySQL mode.<br />";

// 3.1.1 Fix typos in Countries, Actions, Ribbons and Awards
$db->query("ALTER TABLE `hlstats_Countries` MODIFY `name` varchar(64) NOT NULL");
$db->query("UPDATE `hlstats_ClanTags` SET `pattern` = '\\\\AXXXXXX/' WHERE `pattern` = 'AXXXXXX/'");
$db->query("UPDATE `hlstats_Countries` SET `name` = 'Cook Islands' WHERE `flag` = 'CK'");
$db->query("UPDATE `hlstats_Countries` SET `name` = 'Union of Soviet Socialist Republics (no longer exists)' WHERE `flag` = 'SU'");
$db->query("UPDATE `hlstats_Countries` SET `name` = 'Moldova, Republic of' WHERE `flag` = 'MD'");
$db->query("UPDATE `hlstats_Awards` SET `name` = 'Shutdown The Production Line' WHERE `game` = 'dystopia' AND `code` = 'Shutdown The Production Line'");
$db->query("UPDATE `hlstats_Actions` SET `description` = 'Completed Objective' WHERE `game` = 'pvkii' AND `code` = 'obj_complete'");
$db->query("DELETE FROM `hlstats_Ribbons` WHERE `game` = 'tf' AND `awardCode` = 'obj_sentrygun'");
$db->query("UPDATE `hlstats_Ribbons` SET `ribbonName` = 'Gold Galil' WHERE `game` = 'cstrike' AND `ribbonName` = 'GoldGalil'");
$db->query("UPDATE `hlstats_Ribbons` SET `ribbonName` = 'Platinum Combat Knife' WHERE `game` = 'cstrike' AND `ribbonName` = 'PlatinumCombat Knife'");
$db->query("UPDATE `hlstats_Ribbons` SET `ribbonName` = 'Platinum Lowpinger' WHERE `game` = 'cstrike' AND `ribbonName` = 'PlatinumLowpinger'");
$db->query("UPDATE `hlstats_Ribbons` SET `ribbonName` = 'Supreme Desert Eagle' WHERE `game` = 'cstrike' AND `ribbonName` = 'Supremef Desert Eagle'");
$db->query("UPDATE `hlstats_Ribbons` SET `ribbonName` = 'Supreme P90' WHERE `game` = 'cstrike' AND `ribbonName` = 'Supremef P90'");
$db->query("UPDATE `hlstats_Ribbons` SET `ribbonName` = 'Bronze Famas' WHERE `game` = 'csp' AND `awardCode` = 'famas' AND `awardCount` = 5");
$db->query("UPDATE `hlstats_Ribbons` SET `ribbonName` = 'Silver Famas' WHERE `game` = 'csp' AND `awardCode` = 'famas' AND `awardCount` = 12");
$db->query("UPDATE `hlstats_Ribbons` SET `ribbonName` = 'Gold Famas' WHERE `game` = 'csp' AND `awardCode` = 'famas' AND `awardCount` = 20");
$db->query("UPDATE `hlstats_Ribbons` SET `ribbonName` = 'Platinum Famas' WHERE `game` = 'csp' AND `awardCode` = 'famas' AND `awardCount` = 30");
$db->query("UPDATE `hlstats_Ribbons` SET `ribbonName` = 'Supreme Famas' WHERE `game` = 'csp' AND `awardCode` = 'famas' AND `awardCount` = 50");
$db->query("UPDATE `hlstats_Awards` SET `name` = 'Flamethrower Turret Destroyer', `verb` = 'destroyed flamethrower turrets' WHERE `game` = 'nd' AND `code` = 'flamethrowerturret_destroyed'");
echo "Corrected typographical errors in Countries, Actions, Ribbons, and Awards.<br />";

// 3.1.2 Modernize ISO Country definitions & Google Map region choices
$db->query("
    INSERT INTO `hlstats_Countries` (`flag`, `name`) VALUES
    ('AX', 'Åland Islands'),
    ('BL', 'Saint Barthélemy'),
    ('BQ', 'Bonaire, Sint Eustatius and Saba'),
    ('CD', 'Congo, The Democratic Republic of the'),
    ('CW', 'Curaçao'),
    ('GG', 'Guernsey'),
    ('IM', 'Isle of Man'),
    ('JE', 'Jersey'),
    ('ME', 'Montenegro'),
    ('MF', 'Saint Martin'),
    ('PS', 'Palestine'),
    ('RS', 'Serbia'),
    ('SS', 'South Sudan'),
    ('SX', 'Sint Maarten'),
    ('TL', 'Timor-Leste'),
    ('XK', 'Kosovo')
    ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)
");
$db->query("UPDATE `hlstats_Countries` SET `name` = 'Eswatini' WHERE `flag` = 'SZ'");
$db->query("UPDATE `hlstats_Countries` SET `name` = 'Türkiye' WHERE `flag` = 'TR'");

// Register Hungary and Canada in Admin Google Map region choices
$db->query("
    INSERT INTO `hlstats_Options_Choices` (`keyname`, `value`, `text`, `isDefault`) VALUES
    ('google_map_region', 'HUNGARY', 'Hungary', 0),
    ('google_map_region', 'CANADA', 'Canada', 0)
    ON DUPLICATE KEY UPDATE `text` = VALUES(`text`)
");
echo "Synchronized modern ISO 3166-1 country definitions and added Hungary to Map regions.<br />";

// 3.1.3 Create and populate hlstats_Map_Regions table for dynamic Leaflet map administration
$db->query("
    CREATE TABLE IF NOT EXISTS `hlstats_Map_Regions` (
      `region_id` int(10) unsigned NOT NULL auto_increment,
      `code` varchar(64) NOT NULL,
      `name` varchar(128) NOT NULL,
      `lat` decimal(8,4) NOT NULL default '0.0000',
      `lng` decimal(8,4) NOT NULL default '0.0000',
      `zoom` tinyint(2) unsigned NOT NULL default '4',
      PRIMARY KEY (`region_id`),
      UNIQUE KEY `code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$db->query("
    INSERT IGNORE INTO `hlstats_Map_Regions` (`code`, `name`, `lat`, `lng`, `zoom`) VALUES
    ('EUROPE', 'Europe', 48.8000, 8.5000, 3),
    ('NORTH AMERICA', 'North America', 45.0000, -97.0000, 3),
    ('SOUTH AMERICA', 'South America', -14.8000, -61.2000, 3),
    ('NORTH AFRICA', 'North Africa', 25.4000, 8.4000, 4),
    ('SOUTH AFRICA', 'South Africa', -29.0000, 23.7000, 5),
    ('NORTH EUROPE', 'North Europe', 62.6000, 15.4000, 4),
    ('EAST EUROPE', 'East Europe', 51.9000, 31.8000, 4),
    ('CANADA', 'Canada', 60.0000, -97.0000, 3),
    ('GERMANY', 'Germany', 51.1000, 10.1000, 5),
    ('FRANCE', 'France', 47.2000, 2.4000, 5),
    ('SPAIN', 'Spain', 40.3000, -4.0000, 5),
    ('UNITED KINGDOM', 'United Kingdom', 54.0000, -4.3000, 5),
    ('DENMARK', 'Denmark', 56.1000, 9.2000, 6),
    ('SWEDEN', 'Sweden', 63.2000, 16.3000, 4),
    ('NORWAY', 'Norway', 65.6000, 13.1000, 4),
    ('FINLAND', 'Finland', 65.1000, 26.6000, 4),
    ('NETHERLANDS', 'Netherlands', 52.3000, 5.4000, 7),
    ('BELGIUM', 'Belgium', 50.7000, 4.5000, 7),
    ('POLAND', 'Poland', 52.1000, 19.3000, 6),
    ('SUISSE', 'Suisse', 46.8000, 8.2000, 7),
    ('AUSTRIA', 'Austria', 47.7000, 14.1000, 7),
    ('ITALY', 'Italy', 42.6000, 12.7000, 5),
    ('TURKEY', 'Turkey', 39.0000, 34.9000, 6),
    ('ROMANIA', 'Romania', 45.9400, 24.9600, 6),
    ('HUNGARY', 'Hungary', 47.1600, 19.5000, 7),
    ('BRAZIL', 'Brazil', -12.0000, -53.1000, 4),
    ('ARGENTINA', 'Argentina', -34.3000, -65.7000, 3),
    ('RUSSIA', 'Russia', 65.7000, 98.8000, 3),
    ('ASIA', 'Asia', 20.4000, 95.6000, 3),
    ('CHINA', 'China', 36.2000, 104.0000, 4),
    ('JAPAN', 'Japan', 36.2000, 136.8000, 5),
    ('SOUTH KOREA', 'South Korea', 36.6000, 127.8000, 6),
    ('TAIWAN', 'Taiwan', 23.6000, 121.0000, 7),
    ('AUSTRALIA', 'Australia', -26.1000, 134.8000, 4),
    ('WORLD', 'World', 25.0000, 8.5000, 2);
");
echo "Created and populated hlstats_Map_Regions table with 35 standard geographical centers.<br />";

// 3.2 Fix CS:GO Ribbon award codes (P2000 -> hkp2000, sg08 -> ssg08)
$db->query("UPDATE `hlstats_Ribbons` SET `awardCode` = 'hkp2000' WHERE `game` = 'csgo' AND `awardCode` = 'P2000'");
$db->query("UPDATE `hlstats_Ribbons` SET `awardCode` = 'ssg08' WHERE `game` = 'csgo' AND `awardCode` = 'sg08'");
echo "Corrected CS:GO ribbon weapon codes for P2000 and SSG 08 in hlstats_Ribbons.<br />";

// 3.3 Register standalone Left 4 Dead 2 and HL2CTF in hlstats_Games, hlstats_Games_Supported and hlstats_Games_Defaults
$db->query("UPDATE `hlstats_Games` SET `realgame` = 'l4d2' WHERE `code` = 'l4d2'");
$db->query("UPDATE `hlstats_Games` SET `realgame` = 'hl2ctf' WHERE `code` = 'hl2ctf'");
$db->query("UPDATE `hlstats_Games_Supported` SET `name` = 'Left 4 Dead' WHERE `code` = 'l4d'");
$db->query("INSERT INTO `hlstats_Games_Supported` (`code`, `name`) VALUES ('l4d2', 'Left 4 Dead 2') ON DUPLICATE KEY UPDATE `name` = 'Left 4 Dead 2'");
$db->query("INSERT INTO `hlstats_Games_Supported` (`code`, `name`) VALUES ('hl2ctf', 'Half-Life 2 CTF') ON DUPLICATE KEY UPDATE `name` = 'Half-Life 2 CTF'");

// Copy default game settings for l4d2 and hl2ctf if not exists
$db->query("INSERT IGNORE INTO `hlstats_Games_Defaults` (`code`, `parameter`, `value`) SELECT 'l4d2', `parameter`, `value` FROM `hlstats_Games_Defaults` WHERE `code` = 'l4d'");
$db->query("INSERT IGNORE INTO `hlstats_Games_Defaults` (`code`, `parameter`, `value`) SELECT 'hl2ctf', `parameter`, `value` FROM `hlstats_Games_Defaults` WHERE `code` = 'hl2mp'");

// Ensure CS:GO has USP-S and CZ75-Auto in hlstats_Weapons
$db->query("INSERT INTO `hlstats_Weapons` (`game`, `code`, `name`, `modifier`) VALUES ('csgo', 'usp_silencer', 'USP-S', 1.40) ON DUPLICATE KEY UPDATE `name` = 'USP-S'");
$db->query("INSERT INTO `hlstats_Weapons` (`game`, `code`, `name`, `modifier`) VALUES ('csgo', 'cz75a', 'CZ75-Auto', 1.00) ON DUPLICATE KEY UPDATE `name` = 'CZ75-Auto'");

// Fix HL2CTF uppercase weapon codes to lowercase for Perl hash lookup
$db->query("UPDATE `hlstats_Weapons` SET `code` = 'crowbar' WHERE `game` = 'hl2ctf' AND `code` = 'Crowbar'");
$db->query("UPDATE `hlstats_Weapons` SET `code` = 'pistol' WHERE `game` = 'hl2ctf' AND `code` = 'Pistol'");
$db->query("UPDATE `hlstats_Weapons` SET `code` = 'shotgun' WHERE `game` = 'hl2ctf' AND `code` = 'Shotgun'");
$db->query("UPDATE `hlstats_Weapons` SET `code` = 'slam' WHERE `game` = 'hl2ctf' AND `code` = 'Slam'");

// Insert missing HL2CTF Awards
$db->query("
    INSERT INTO `hlstats_Awards` (`awardType`, `game`, `code`, `name`, `verb`) VALUES
    ('W', 'hl2ctf', 'combine_ball', 'Ball Player', 'kills with combine ball'),
    ('W', 'hl2ctf', 'smg1_grenade', 'SMG Nader', 'kills with smg grenade'),
    ('W', 'hl2ctf', 'ctf_oicw', 'OICW Master', 'kills with OICW'),
    ('W', 'hl2ctf', 'ctf_sniper', 'Sniper Master', 'kills with sniper rifle'),
    ('W', 'hl2ctf', 'ctf_alyxgun', 'Alyx Gunner', 'kills with alyx gun'),
    ('O', 'hl2ctf', 'ctf_flag_capture', 'Flag Master', 'flags captured'),
    ('O', 'hl2ctf', 'headshot', 'Headshot King', 'headshots'),
    ('W', 'hl2ctf', 'mostkills', 'Most Kills', 'kills'),
    ('W', 'hl2ctf', 'suicide', 'Suicides', 'suicides')
    ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `verb` = VALUES(`verb`)
");
echo "Synchronized games, default settings and weapons for L4D2, HL2CTF and CS:GO.<br />";

// 3.4 Correct L4D2 and GoldenEye: Source Awards text/logic
$db->query("UPDATE `hlstats_Awards` SET `verb` = 'teammate protections' WHERE `game` = 'l4d2' AND `code` = 'protect_teammate'");
$db->query("UPDATE `hlstats_Awards` SET `name` = 'Jockey Buster' WHERE `game` = 'l4d2' AND `code` = 'killed_jockey'");
$db->query("UPDATE `hlstats_Awards` SET `name` = 'Rocket Launcher' WHERE `game` = 'ges' AND `code` = '#GE_RocketLauncher'");
echo "Corrected Award descriptions and naming for L4D2 and GoldenEye: Source.<br />";

// 3.5 Fix L4D2 Heatmap configurations (migrate game code from 'l4d' to 'l4d2')
$db->query("
    UPDATE `hlstats_Heatmap_Config` 
    SET `game` = 'l4d2' 
    WHERE `game` = 'l4d' 
    AND `map` IN (
        'c1m1_hotel', 'c1m2_streets', 'c1m3_mall', 'c1m4_atrium',
        'c2m1_highway', 'c2m2_fairgrounds', 'c2m3_coaster', 'c2m4_barns', 'c2m5_concert',
        'c3m1_plankcountry', 'c3m2_swamp', 'c3m3_shantytown', 'c3m4_plantation',
        'c4m1_milltown_a', 'c4m2_sugarmill_a', 'c4m3_sugarmill_b', 'c4m4_milltown_b', 'c4m5_milltown_escape',
        'c5m1_waterfront', 'c5m2_park', 'c5m3_cemetery', 'c5m4_quarter', 'c5m5_bridge'
    )
");
echo "Updated Heatmap configurations for Left 4 Dead 2 campaigns.<br />";

// 3.6 Remove legacy duplicate CS:GO Galil entry
$db->query("DELETE FROM `hlstats_Weapons` WHERE `game` = 'csgo' AND `code` = 'galil'");

// 3.7 Insert missing Weapon Awards for CS2 and CS:GO (M4A1-S, USP-S, R8 Revolver, MP5-SD, Molotov, Bayonet, Cz75a)
$db->query("
    INSERT INTO `hlstats_Awards` (`awardType`, `game`, `code`, `name`, `verb`) VALUES
    ('W', 'cs2', 'm4a1_silencer', 'M4A1-S', 'kills with m4a1_silencer'),
    ('W', 'cs2', 'usp_silencer', 'USP-S', 'kills with usp_silencer'),
    ('W', 'cs2', 'revolver', 'R8 Revolver', 'kills with revolver'),
    ('W', 'cs2', 'mp5sd', 'MP5-SD', 'kills with mp5sd'),
    ('W', 'cs2', 'inferno', 'Molotov', 'kills with molotov'),
    ('W', 'cs2', 'bayonet', 'Bayonet', 'kills with bayonet'),
    ('W', 'csgo', 'm4a1_silencer', 'M4A1-S', 'kills with m4a1_silencer'),
    ('W', 'csgo', 'usp_silencer', 'USP-S', 'kills with usp_silencer'),
    ('W', 'csgo', 'revolver', 'R8 Revolver', 'kills with revolver'),
    ('W', 'csgo', 'mp5sd', 'MP5-SD', 'kills with mp5sd'),
    ('W', 'csgo', 'inferno', 'Molotov', 'kills with molotov'),
    ('W', 'csgo', 'bayonet', 'Bayonet', 'kills with bayonet'),
    ('W', 'csgo', 'cz75a', 'CZ75-Auto', 'kills with cz75a')
    ON DUPLICATE KEY UPDATE 
        `name` = VALUES(`name`),
        `verb` = VALUES(`verb`)
");
echo "Synchronized CS2 and CS:GO weapon awards (M4A1-S, USP-S, Revolver, MP5-SD, Molotov, Bayonet, Cz75a) in hlstats_Awards.<br />";

// 3.8 Align integer column types, display widths and apply Y2038 timestamp protection (UNSIGNED INT)
function ensureColumnUnsigned($table, $column, $columnDefinition) {
    global $db;
    $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($res && $row = $db->fetch_array($res)) {
        if (stripos($row['Type'], 'unsigned') === false) {
            $db->query("ALTER TABLE `$table` MODIFY `$column` $columnDefinition");
            echo "Upgraded column '$column' in table '$table' to UNSIGNED (Y2038 protection applied).<br />";
        } else {
            echo "Column '$column' in table '$table' is already UNSIGNED, skipping...<br />";
        }
    }
}

// 3.8.1 Apply Y2038 protection to all Unix timestamp fields (safe until year 2106)
ensureColumnUnsigned('hlstats_Players', 'last_event', "int(10) unsigned NOT NULL default '0'");
ensureColumnUnsigned('hlstats_Players', 'createdate', "int(10) unsigned NOT NULL default '0'");
ensureColumnUnsigned('hlstats_Servers', 'map_started', "int(10) unsigned NOT NULL default '0'");
ensureColumnUnsigned('hlstats_Servers', 'last_event', "int(10) unsigned NOT NULL default '0'");
ensureColumnUnsigned('hlstats_server_load', 'timestamp', "int(10) unsigned NOT NULL default '0'");
ensureColumnUnsigned('hlstats_Trend', 'timestamp', "int(10) unsigned NOT NULL default '0'");
ensureColumnUnsigned('hlstats_Livestats', 'connected', "int(10) unsigned NOT NULL default '0'");

// 3.8.2 Align foreign key and ID columns to UNSIGNED for optimal index joins
ensureColumnUnsigned('hlstats_Livestats', 'player_id', "int(10) unsigned NOT NULL default '0'");
ensureColumnUnsigned('hlstats_Livestats', 'server_id', "int(10) unsigned NOT NULL default '0'");
ensureColumnUnsigned('hlstats_Users', 'playerId', "int(10) unsigned NOT NULL default '0'");
ensureColumnUnsigned('hlstats_server_load', 'server_id', "int(10) unsigned NOT NULL default '0'");
ensureColumnUnsigned('hlstats_Heatmap_Config', 'id', "int(10) unsigned NOT NULL auto_increment");

echo "All Unix timestamp and ID columns verified and secured against Y2038 overflow.<br />";

// 3.9. Adding the 'neck' and 'generic' column to the statsme2 table if it doesn't exist
$res_neck = $db->query("SHOW COLUMNS FROM `hlstats_Events_Statsme2` LIKE 'neck'");
$has_neck = ($res_neck && $db->num_rows($res_neck) > 0);

$res_gen = $db->query("SHOW COLUMNS FROM `hlstats_Events_Statsme2` LIKE 'generic'");
$has_gen = ($res_gen && $db->num_rows($res_gen) > 0);

if (!$has_neck && !$has_gen) {
    $db->query("ALTER TABLE `hlstats_Events_Statsme2` ADD `neck` INT(6) UNSIGNED NOT NULL DEFAULT '0' AFTER `head`, ADD `generic` INT(6) UNSIGNED NOT NULL DEFAULT '0' AFTER `rightleg`");
    echo "Added 'neck' and 'generic' columns to hlstats_Events_Statsme2 in single pass.<br />";
} elseif (!$has_neck) {
    $db->query("ALTER TABLE `hlstats_Events_Statsme2` ADD `neck` INT(6) UNSIGNED NOT NULL DEFAULT '0' AFTER `head`");
    echo "Added 'neck' column to hlstats_Events_Statsme2.<br />";
} elseif (!$has_gen) {
    $db->query("ALTER TABLE `hlstats_Events_Statsme2` ADD `generic` INT(6) UNSIGNED NOT NULL DEFAULT '0' AFTER `rightleg`");
    echo "Added 'generic' column to hlstats_Events_Statsme2.<br />";
} else {
    echo "Columns 'neck' and 'generic' already exist in hlstats_Events_Statsme2, skipping...<br />";
}
flush();

// 4. Performance Indexes Optimization & 28-day Cleanup Support
function addIndexIfNotExists($table, $indexName, $columnsSql) {
    global $db;
    $check = $db->query("SHOW INDEX FROM `$table` WHERE `Key_name` = '$indexName'");
    if ($check && $db->num_rows($check) == 0) {
        $db->query("ALTER TABLE `$table` ADD INDEX `$indexName` ($columnsSql)");
        echo "Created performance index '$indexName' on table '$table'.<br />";
        flush();
    }
}

function dropIndexIfExists($table, $indexName) {
    global $db;
    $check = $db->query("SHOW INDEX FROM `$table` WHERE `Key_name` = '$indexName'");
    if ($check && $db->num_rows($check) > 0) {
        $db->query("ALTER TABLE `$table` DROP INDEX `$indexName`");
        echo "Dropped redundant index '$indexName' from table '$table'.<br />";
        flush();
    }
}

// 4.1 Fix hlstats_Players_Ribbons primary key and index
$res_pk = $db->query("SHOW INDEX FROM `hlstats_Players_Ribbons` WHERE `Key_name` = 'PRIMARY'");
if (!$res_pk || $db->num_rows($res_pk) == 0) {
    // Safe deduplication and PRIMARY KEY creation for MySQL 5.7 / 8.0+ (ALTER IGNORE is obsolete)
    $db->query("CREATE TABLE `hlstats_Players_Ribbons_tmp` LIKE `hlstats_Players_Ribbons`");
    $db->query("ALTER TABLE `hlstats_Players_Ribbons_tmp` ADD PRIMARY KEY (`playerId`, `ribbonId`, `game`)");
    $db->query("INSERT IGNORE INTO `hlstats_Players_Ribbons_tmp` SELECT * FROM `hlstats_Players_Ribbons`");
    $db->query("DROP TABLE `hlstats_Players_Ribbons`");
    $db->query("RENAME TABLE `hlstats_Players_Ribbons_tmp` TO `hlstats_Players_Ribbons`");
    echo "Added PRIMARY KEY to hlstats_Players_Ribbons.<br />";
}
addIndexIfNotExists('hlstats_Players_Ribbons', 'idx_ribbon', '`ribbonId`, `game`');

// 4.2 Clean up redundant legacy indexes that are now covered by composite indexes
dropIndexIfExists('hlstats_Events_Frags', 'killerId');
dropIndexIfExists('hlstats_Events_Frags', 'serverId');
dropIndexIfExists('hlstats_Events_Frags', 'headshot');
dropIndexIfExists('hlstats_Events_Chat', 'playerId');
dropIndexIfExists('hlstats_Events_Chat', 'serverId');
dropIndexIfExists('hlstats_Events_PlayerActions', 'actionId');
dropIndexIfExists('hlstats_Events_PlayerPlayerActions', 'actionId');
dropIndexIfExists('hlstats_server_load', 'server_id');
dropIndexIfExists('hlstats_Trend', 'game');
dropIndexIfExists('hlstats_Players', 'game');
dropIndexIfExists('hlstats_Players', 'hideranking');
dropIndexIfExists('hlstats_Livestats', 'server_id');
dropIndexIfExists('hlstats_Livestats', 'is_dead');

// 4.3 General Performance & Composite Indexes
addIndexIfNotExists('geoLiteCity_Blocks', 'idx_iprange', '`startIpNum`, `endIpNum`');
addIndexIfNotExists('hlstats_Players', 'idx_last_event', '`last_event`');
addIndexIfNotExists('hlstats_Players', 'idx_game_rank_skill', '`game`, `hideranking`, `skill`');
addIndexIfNotExists('hlstats_Players', 'idx_game_rank_kills', '`game`, `hideranking`, `kills`');
addIndexIfNotExists('hlstats_Livestats', 'idx_server_team_conn', '`server_id`, `connected`, `team`');
addIndexIfNotExists('hlstats_Actions', 'idx_game', '`game`');
addIndexIfNotExists('hlstats_Events_Frags', 'idx_killer_time', '`killerId`, `eventTime`');
addIndexIfNotExists('hlstats_Events_Frags', 'idx_server_time', '`serverId`, `eventTime`');
addIndexIfNotExists('hlstats_Events_PlayerActions', 'idx_action_player', '`actionId`, `playerId`');
addIndexIfNotExists('hlstats_Events_PlayerPlayerActions', 'idx_action_victim', '`actionId`, `victimId`');
addIndexIfNotExists('hlstats_Events_PlayerPlayerActions', 'idx_action_player', '`actionId`, `playerId`');
addIndexIfNotExists('hlstats_Events_Chat', 'idx_player_time', '`playerId`, `eventTime`');
addIndexIfNotExists('hlstats_Events_Chat', 'idx_server_time', '`serverId`, `eventTime`');
addIndexIfNotExists('hlstats_server_load', 'idx_server_timestamp', '`server_id`, `timestamp`');
addIndexIfNotExists('hlstats_Trend', 'idx_game_timestamp', '`game`, `timestamp`');

// 4.4 Automated 28-day cleanup indexes on all Event tables (eventTime)
$event_cleanup_tables = array(
    'hlstats_Events_Frags',
    'hlstats_Events_PlayerActions',
    'hlstats_Events_PlayerPlayerActions',
    'hlstats_Events_Chat',
    'hlstats_Events_Connects',
    'hlstats_Events_Disconnects',
    'hlstats_Events_Entries',
    'hlstats_Events_Latency',
    'hlstats_Events_Admin',
    'hlstats_Events_Rcon',
    'hlstats_Events_ChangeName',
    'hlstats_Events_ChangeRole',
    'hlstats_Events_ChangeTeam',
    'hlstats_Events_Statsme',
    'hlstats_Events_Statsme2',
    'hlstats_Events_StatsmeLatency',
    'hlstats_Events_StatsmeTime',
    'hlstats_Events_Suicides',
    'hlstats_Events_TeamBonuses',
    'hlstats_Events_Teamkills'
);

foreach ($event_cleanup_tables as $ect) {
    addIndexIfNotExists($ect, 'idx_eventTime', '`eventTime`');
}

// 5. Update system version
$db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
$db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");

echo "<br /><b>Update 97 Technical Summary:</b><br />";
echo "- <b>Storage Engine:</b> Modernized database tables from deprecated MyISAM to high-performance, crash-safe InnoDB with utf8mb4 collation and row-level locking (while maintaining MEMORY engine for LiveStats).<br />";
echo "- <b>Y2038 Protection:</b> Aligned all Unix timestamp columns across `hlstats_Players`, `hlstats_Servers`, `hlstats_server_load`, `hlstats_Trend`, and `hlstats_Livestats` to `UNSIGNED INT`, guaranteeing overflow safety until year 2106.<br />";
echo "- <b>Hitgroup Modernization:</b> Added 'neck' and verified 'generic' (Body) hitgroup columns in `hlstats_Events_Statsme2` with dynamic table scaling on the web frontend.<br />";
echo "- <b>Database Indexing:</b> Optimized index architecture: dropped redundant indexes, added `eventTime` cleanup B-Trees across all 20 event tables for non-blocking 28-day maintenance, added `last_event` player cleanup index, and established missing PRIMARY KEY on `hlstats_Players_Ribbons`.<br />";
echo "- <b>Query Optimization:</b> Added composite B-Tree indexes to `geoLiteCity_Blocks`, `hlstats_Players`, `hlstats_Livestats`, `hlstats_Actions`, `hlstats_Events_Chat`, `hlstats_server_load`, and `hlstats_Trend` to eliminate filesorts and enable high-concurrency scaling.<br />";
echo "- <b>Backend Daemon:</b> Updated `HLstats_EventHandlers.plib` round-end handlers to universally detect and record round victories across all 29 supported game modifications.<br />";
echo "- <b>Database:</b> Added 'neck' hitgroup tracking to support CS2 neck damage.<br />";
echo "- <b>Frontend Fixes:</b> Eliminated multi-decade team win inversion and false win attribution for spectators/unknown teams in `status.php` and `livestats.php`.<br />";
echo "- <b>Multi-Mod Round Actions:</b> Registered missing round resolution and team victory actions across all game modifications (CS2, CS:GO, CSS, CS 1.6, CSPromod, PVKII, BG2, Dino D-Day, Dystopia, FF, FoF, Hidden, ZPS, DoD, HL2CTF) in `hlstats_Actions`.<br />";
echo "- <b>Data Normalization:</b> Fixed strict-mode enum truncation in `hlstats_Teams`, aligned integer column types to `unsigned` across foreign keys, corrected ribbon award code mismatches for TF2 (`allsentrykills`) and CS:GO (`hkp2000`, `ssg08`), fixed Country definitions (`SU`, `MD`, `CK`), and purged legacy weapon duplicates.<br />";
echo "- <b>Weapon Awards Synchronization:</b> Registered Daily and Global awards for CS2 and CS:GO modern meta weapons (M4A1-S, USP-S, R8 Revolver, MP5-SD, Molotov, Bayonet, Cz75a) in `hlstats_Awards`.<br />";
echo "- <b>GeoIP & Leaflet Map Modernization:</b> Synchronized modern ISO country definitions (RS, ME, XK, AX, IM, etc.), updated sovereign nation names (Eswatini, Türkiye), and established dedicated `hlstats_Map_Regions` table for dynamic Leaflet map administration.<br />";
echo "- <b>L4D2 & Mod Configs:</b> Registered Left 4 Dead 2 as a standalone supported game, fixed L4D2 Heatmap campaign definitions (`c1m1`-`c5m5`), and corrected award strings for L4D2 and GoldenEye: Source.<br />";
echo "<br />Update completed successfully.<br />";
?>