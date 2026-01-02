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

    if (!defined('IN_HLSTATS')) {
	die('Do not access this file directly.');
    }

    global $db, $game, $g_options;

    // Player Details
    
    // PHP 8 Fix: Null coalescing and type casting
    $player_in = isset($_GET['player']) ? $_GET['player'] : 0;
    $player = valid_request((int)$player_in, true);
    
    $uniqueid_in = isset($_GET['uniqueid']) ? $_GET['uniqueid'] : '';
    $uniqueid = valid_request((string)$uniqueid_in, false);
    
    $game_in = isset($_GET['game']) ? $_GET['game'] : '';
    $game = valid_request((string)$game_in, false);
    
    // Security: Escape variables
    $uniqueid_esc = $db->escape($uniqueid);
    $game_esc = $db->escape($game);

    if (!$player && $uniqueid) {
	if (!$game) {
            $redirect_url = $g_options['scripturl'] . "&mode=search&st=uniqueid&q=" . urlencode($uniqueid);
	    header("Location: $redirect_url");
	    exit;
	}

	$db->query("
	    SELECT
		playerId
	    FROM
		hlstats_PlayerUniqueIds
	    WHERE
		uniqueId='$uniqueid_esc'
		AND game='$game_esc'
	");
	
	if ($db->num_rows() > 1) {
            $redirect_url = $g_options['scripturl'] . "&mode=search&st=uniqueid&q=" . urlencode($uniqueid) . "&game=" . urlencode($game);
	    header("Location: $redirect_url");
	    exit;
	} elseif ($db->num_rows() < 1) {
	    error("No players found matching uniqueId '$uniqueid'");
	} else {
            // PHP 8 Fix: Replace list()
            $row = $db->fetch_row();
	    $player = (int)$row[0];
	}
    } elseif (!$player && !$uniqueid) {
	error('No player ID specified.');
    }
    
    $db->query("
	SELECT
	    hlstats_Players.playerId,
	    hlstats_Players.lastName,
	    hlstats_Players.game
	FROM
	    hlstats_Players
	WHERE
	    playerId='$player'
    ");

    if ($db->num_rows() != 1) {
	error("No such player '$player'.");
    }

    $playerdata = $db->fetch_array();
    $db->free_result();
    
    // PHP 8 Fix: Handle null name
    $pl_name = isset($playerdata['lastName']) ? $playerdata['lastName'] : '';
    
    if (strlen((string)$pl_name) > 10) {
	$pl_shortname = substr($pl_name, 0, 8) . '...';
    } else {
	$pl_shortname = $pl_name;
    }

    $pl_name = htmlspecialchars((string)$pl_name, ENT_COMPAT);
    $pl_shortname = htmlspecialchars((string)$pl_shortname, ENT_COMPAT);
    $pl_urlname = urlencode(isset($playerdata['lastName']) ? $playerdata['lastName'] : '');

    $game = isset($playerdata['game']) ? $playerdata['game'] : '';
    $game_esc = $db->escape($game);
    
    $db->query("SELECT name FROM hlstats_Games WHERE code='$game_esc'");
    if ($db->num_rows() != 1) {
	$gamename = ucfirst($game);
    } else {
        // PHP 8 Fix: Replace list()
        $row = $db->fetch_row();
	$gamename = $row[0];
    }
    
    // Added: Page Header for proper layout
    pageHeader(
	array ($gamename, 'Weapon Usage', $pl_name),
	array ($gamename=>"%s?game=$game", 'Weapon Usage'=>'')
    );
    
    // Get Weapon Name
    $result = $db->query
    ("
	SELECT
	    hlstats_Weapons.code,
	    hlstats_Weapons.name
	FROM
	    hlstats_Weapons
	WHERE
	    hlstats_Weapons.game = '$game_esc'
    ");
    $fname = array();
    while ($rowdata = $db->fetch_row($result))
    { 
	$code = $rowdata[0];
        // PHP 8 Fix: Explicit string cast
	$fname[strtolower((string)$code)] = $rowdata[1];
    }

    $tblWeapons = new Table(
	array(
	    new TableColumn(
		'weapon',
		'Weapon',
		'width=15&type=weaponimg&align=center&link=' . urlencode("mode=weaponinfo&weapon=%k&game=$game"),
		$fname
	    ),
	    new TableColumn(
		'modifier',
		'Modifier',
		'width=10&align=right'
	    ),
	    new TableColumn(
		'kills',
		'Kills',
		'width=11&align=right'
	    ),
	    new TableColumn(
		'kpercent',
		'Perc. Kills',
		'width=18&sort=no&type=bargraph'
	    ),
	    new TableColumn(
		'kpercent',
		'%',
		'width=5&sort=no&align=right&append=' . urlencode('%')
	    ),
	    new TableColumn(
		'headshots',
		'Headshots',
		'width=8&align=right'
	    ),
	    new TableColumn(
		'hpercent',
		'Perc. Headshots',
		'width=18&sort=no&type=bargraph'
	    ),
	    new TableColumn(
		'hpercent',
		'%',
		'width=5&sort=no&align=right&append=' . urlencode('%')
	    ),
	    new TableColumn(
		'hpk',
		'Hpk',
		'width=5&align=right'
	    )
	),
	'weapon',
	'kills',
	'weapon',
	true,
	9999,
	'weap_page',
	'weap_sort',
	'weap_sortorder',
	'weapons'
    );
    
    $db->query("
	    SELECT
		COUNT(*)
	    FROM
		hlstats_Events_Frags
	    LEFT JOIN hlstats_Servers ON
		hlstats_Servers.serverId=hlstats_Events_Frags.serverId
	    WHERE
		hlstats_Servers.game='$game_esc' AND killerId='$player'
    ");

    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row();
    $realkills = ($row) ? (int)$row[0] : 0;

    $db->query("
	    SELECT
		COUNT(*)
	    FROM
		hlstats_Events_Frags
	    LEFT JOIN hlstats_Servers ON
		hlstats_Servers.serverId=hlstats_Events_Frags.serverId
	    WHERE
		hlstats_Servers.game='$game_esc' AND killerId='$player'
		AND headshot=1      
    ");

    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row();
    $realheadshots = ($row) ? (int)$row[0] : 0;
    
    // Prevent division by zero
    $realkills_sql = ($realkills > 0) ? $realkills : 1;
    $realheadshots_sql = ($realheadshots > 0) ? $realheadshots : 1;

    $result = $db->query("
	SELECT
	    hlstats_Events_Frags.weapon,
	    IFNULL(hlstats_Weapons.modifier, 1.00) AS modifier,
	    COUNT(hlstats_Events_Frags.weapon) AS kills,
	    ROUND(COUNT(hlstats_Events_Frags.weapon) / $realkills_sql * 100, 2) AS kpercent,
	    SUM(hlstats_Events_Frags.headshot=1) as headshots,
	    SUM(hlstats_Events_Frags.headshot=1) / COUNT(hlstats_Events_Frags.weapon) AS hpk,
	    ROUND(SUM(hlstats_Events_Frags.headshot=1) / $realheadshots_sql * 100, 2) AS hpercent
	FROM
	    hlstats_Events_Frags
	LEFT JOIN hlstats_Weapons ON
	    hlstats_Weapons.code = hlstats_Events_Frags.weapon
	LEFT JOIN hlstats_Servers ON
	    hlstats_Servers.serverId=hlstats_Events_Frags.serverId
	WHERE
	    hlstats_Servers.game='$game_esc' AND hlstats_Events_Frags.killerId='$player'
	    AND (hlstats_Weapons.game='$game_esc' OR hlstats_Weapons.weaponId IS NULL)
	GROUP BY
	    hlstats_Events_Frags.weapon,
	    hlstats_Weapons.modifier
	ORDER BY
	    $tblWeapons->sort $tblWeapons->sortorder,
	    $tblWeapons->sort2 $tblWeapons->sortorder
    ");

	$tblWeapons->draw($result, $db->num_rows($result), 100);
?>