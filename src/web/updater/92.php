<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 92;
    $version = "1.12.0";

    echo "Executing update script 92...<br />";

    // 1. Global Fixes
    $db->query("UPDATE `hlstats_Options` SET `value` = '0' WHERE `keyname` = 'show_weapon_target_flash'");

    // 2. Inserting missing headshot actions line
    $db->query("INSERT IGNORE INTO `hlstats_Actions` (`game`, `code`, `reward_player`, `reward_team`, `team`, `description`, `for_PlayerActions`, `for_PlayerPlayerActions`, `for_TeamActions`, `for_WorldActions`, `count`) VALUES ('dods', 'headshot', 2, 0, '', 'Headshot Kill', '1', '0', '0', '0', 0)");

    // 3. CSS Specific Fixes (Renaming Famas awards)
    $db->query("UPDATE `hlstats_Ribbons` SET `ribbonName` = 'Bronze Famas' WHERE `awardCode` = 'famas' AND `awardCount` = 5 AND `game` = 'css'");
    $db->query("UPDATE `hlstats_Ribbons` SET `ribbonName` = 'Silver Famas' WHERE `awardCode` = 'famas' AND `awardCount` = 12 AND `game` = 'css'");
    $db->query("UPDATE `hlstats_Ribbons` SET `ribbonName` = 'Gold Famas' WHERE `awardCode` = 'famas' AND `awardCount` = 20 AND `game` = 'css'");
    $db->query("UPDATE `hlstats_Ribbons` SET `ribbonName` = 'Platinum Famas' WHERE `awardCode` = 'famas' AND `awardCount` = 30 AND `game` = 'css'");
    $db->query("UPDATE `hlstats_Ribbons` SET `ribbonName` = 'Supreme Famas' WHERE `awardCode` = 'famas' AND `awardCount` = 50 AND `game` = 'css'");

    // 4. CS:GO and CS2 Normalization (AUG case, Remove legacy 'galil' from CS2 to prevent duplication with 'galilar' fix)
    $db->query("UPDATE `hlstats_Weapons` SET `code` = 'aug' WHERE `code` = 'AUG' AND `game` = 'csgo'");
    $db->query("UPDATE `hlstats_Actions` SET `code` = 'aug' WHERE `code` = 'AUG' AND `game` = 'csgo'");
    $db->query("UPDATE `hlstats_Awards` SET `code` = 'aug' WHERE `code` = 'AUG' AND `game` = 'csgo'");
    $db->query("DELETE FROM `hlstats_Weapons` WHERE `game`='cs2' AND `code`='galil'");
    $db->query("DELETE FROM `hlstats_Awards` WHERE `game`='cs2' AND `code`='galil'");
    $db->query("DELETE FROM `hlstats_Ribbons` WHERE `game`='cs2' AND `awardCode`='galil'");

    // 5. CS2 Weapons
    $db->query("
        INSERT IGNORE INTO `hlstats_Weapons` (`game`, `code`, `name`, `modifier`) VALUES
            ('cs2', 'ak47', 'Kalashnikov AK-47', 1.00),
            ('cs2', 'm4a1', 'M4A4', 1.00),
            ('cs2', 'm4a1_silencer', 'M4A1-S', 1.00),
            ('cs2', 'galilar', 'Galil AR', 1.10),
            ('cs2', 'famas', 'FAMAS', 1.00),
            ('cs2', 'aug', 'AUG', 1.00),
            ('cs2', 'sg553', 'SG 553', 1.00),
            ('cs2', 'ssg08', 'SSG 08', 1.10),
            ('cs2', 'awp', 'AWP', 1.00),
            ('cs2', 'g3sg1', 'H&K G3/SG1 Sniper Rifle', 0.80),
            ('cs2', 'scar20', 'SCAR-20', 0.80),
            ('cs2', 'mac10', 'MAC-10', 1.50),
            ('cs2', 'mp9', 'MP9', 1.40),
            ('cs2', 'mp7', 'MP7', 1.30),
            ('cs2', 'mp5sd', 'MP5-SD', 1.00),
            ('cs2', 'ump45', 'UMP-45', 1.20),
            ('cs2', 'p90', 'P90', 1.20),
            ('cs2', 'bizon', 'PP-Bizon', 1.30),
            ('cs2', 'glock', 'Glock-18', 1.40),
            ('cs2', 'hkp2000', 'P2000', 1.40),
            ('cs2', 'usp_silencer', 'USP-S', 1.40),
            ('cs2', 'p250', 'P250', 1.50),
            ('cs2', 'cz75a', 'CZ75-Auto', 1.00),
            ('cs2', 'fiveseven', 'Five-SeveN', 1.50),
            ('cs2', 'tec9', 'Tec-9', 1.20),
            ('cs2', 'deagle', 'Desert Eagle', 1.20),
            ('cs2', 'revolver', 'R8 Revolver', 1.20),
            ('cs2', 'elite', 'Dual Berettas', 1.40),
            ('cs2', 'nova', 'Nova', 1.30),
            ('cs2', 'xm1014', 'XM1014', 1.10),
            ('cs2', 'mag7', 'MAG-7', 1.30),
            ('cs2', 'sawedoff', 'Sawed-Off', 1.30),
            ('cs2', 'm249', 'M249 PARA Light Machine Gun', 1.00),
            ('cs2', 'negev', 'Negev', 1.00),
            ('cs2', 'hegrenade', 'High Explosive Grenade', 1.80),
            ('cs2', 'inferno', 'Molotov', 1.80),
            ('cs2', 'firebomb', 'Incendiary Grenade', 1.80),
            ('cs2', 'taser', 'Zeus x27', 1.00),
            ('cs2', 'flashbang', 'Flashbang Impact', 5.00),
            ('cs2', 'smokegrenade', 'Smoke Grenade Impact', 5.00),
            ('cs2', 'decoy', 'Decoy Grenade Impact', 5.00),
            ('cs2', 'breachcharge', 'Breach Charge', 1.00),
            ('cs2', 'knife', 'Knife', 2.00),
            ('cs2', 'knife_t', 'Terrorist Knife', 2.00),
            ('cs2', 'bayonet', 'Bayonet', 2.00),
            ('cs2', 'knife_butterfly', 'Butterfly Knife', 2.00),
            ('cs2', 'knife_falchion', 'Falchion Knife', 2.00),
            ('cs2', 'knife_flip', 'Flip Knife', 2.00),
            ('cs2', 'knife_gut', 'Gut Knife', 2.00),
            ('cs2', 'knife_karambit', 'Karambit', 2.00),
            ('cs2', 'knife_m9_bayonet', 'M9 Bayonet', 2.00),
            ('cs2', 'knife_push', 'Shadow Daggers', 2.00),
            ('cs2', 'knife_tactical', 'Huntsman Knife', 2.00),
            ('cs2', 'knife_survival_bowie', 'Bowie Knife', 2.00),
            ('cs2', 'knife_ursus', 'Ursus Knife', 2.00),
            ('cs2', 'knife_gypsy_jackknife', 'Navaja Knife', 2.00),
            ('cs2', 'knife_stiletto', 'Stiletto Knife', 2.00),
            ('cs2', 'knife_widowmaker', 'Talon Knife', 2.00),
            ('cs2', 'knife_canis', 'Survival Knife', 2.00),
            ('cs2', 'knife_cord', 'Paracord Knife', 2.00),
            ('cs2', 'knife_skeleton', 'Skeleton Knife', 2.00),
            ('cs2', 'knife_outdoor', 'Nomad Knife', 2.00),
            ('cs2', 'knife_css', 'Classic Knife', 2.00),
            ('cs2', 'knife_kukri', 'Kukri Knife', 2.00),
            ('cs2', 'knife_twinblade', 'Twinblade Knife', 2.00)
    ");

    $db->query("
        INSERT IGNORE INTO `hlstats_Awards` (`awardType`, `game`, `code`, `name`, `verb`)
            SELECT 'W', 'cs2', code, name, 'kills' FROM hlstats_Weapons WHERE game='cs2'
    ");

    // 6. CS2 Ribbons
    $levels = array(
        1  => 'Award of',
        5  => 'Bronze',
        12 => 'Silver',
        20 => 'Gold',
        30 => 'Platinum',
        50 => 'Supreme'
    );

    foreach ($levels as $count => $prefix) {
        $tier = ($count == 1) ? 1 : ($count == 5 ? 2 : ($count == 12 ? 3 : ($count == 20 ? 4 : ($count == 30 ? 5 : 6))));

        $db->query("
            INSERT IGNORE INTO `hlstats_Ribbons` (`awardCode`, `awardCount`, `special`, `game`, `image`, `ribbonName`)
                SELECT code, $count, 0, 'cs2', CONCAT('$tier', '_', code, '.png'), CONCAT('$prefix ', name) 
                FROM hlstats_Weapons WHERE game='cs2'
        ");

        $actions = array(
            'Defused_The_Bomb' => 'Bomb Defuser', 
            'Planted_The_Bomb' => 'Bomb Planter',
            'Rescued_A_Hostage' => 'Hostage Rescuer', 
            'Killed_A_Hostage' => 'Hostage Killer',
            'latency' => 'Lowpinger', 
            'headshot' => 'Headshots', 
            'teamkills' => 'Team Kills',
            'mostkills' => 'Most Kills', 
            'round_mvp' => 'Most Valuable Player', 
            'suicide' => 'Suicides'
        );

        foreach ($actions as $code => $name) {
            if ($count == 1) {
                $rName = ($code == 'suicide') ? "Award of Most Suicides" : "Award of $name";
                if ($code == 'round_mvp') $rName = "Most Valuable Player";
            } else {
                $rName = "$prefix $name";
            }
            $db->query("INSERT IGNORE INTO `hlstats_Ribbons` (`awardCode`, `awardCount`, `special`, `game`, `image`, `ribbonName`)
                        VALUES ('$code', $count, 0, 'cs2', '{$tier}_" . strtolower($code) . ".png', '$rName')");
        }
    }

    // 7. CS2 Specific Cleanup (Self-Healing)
    $db->query("DELETE FROM `hlstats_Maps_Counts` WHERE `map` = '(Unaccounted)' AND `kills` = 0 AND `headshots` = 0");

    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");

    echo "<br /><b>Update 92 Summary:</b><br />";
    echo "- Global: Fixed Flash hitbox display error by setting HTML Hitbox as default.<br />";
    echo "- CSS: Corrected FAMAS Ribbon names across all tiers.<br />";
    echo "- DODS: Fixed missing headshot action tracking.<br />";
    echo "- CS:GO: Normalized legacy weapon code (aug).<br />";
    echo "- CS2: Prevent duplication with 'galilar' fix.<br />";
    echo "- CS2: Successfully inserted 65 weapons and synchronized Awards.<br />";
    echo "- CS2: Generated 390 Ribbons across 6 tiers (1, 5, 12, 20, 30, 50 kills).<br />";
    echo "- CS2: Automatically purged (Unaccounted) map entries with 0 kills.<br />";
    echo "<br />Update completed successfully.<br />";
?>