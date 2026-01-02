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
	array ($gamename, 'Targets', $pl_name),
	array ($gamename=>"%s?game=$game", 'Targets'=>'')
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

    $tblWeaponstats2 = new Table(
	array(
	    new TableColumn(
		'smweapon',
		'Weapon',
		'width=10&type=weaponimg&align=center&link=' . urlencode("mode=weaponinfo&weapon=%k&game=$game"),
		$fname
	    ),
	    new TableColumn(
		'smhits',
		'Hits',
		'width=8&align=right'
	    ),
	    new TableColumn(
		'smhead',
		'Head',
		'width=8&align=right'
	    ),
	    new TableColumn(
		'smchest',
		'Chest',
		'width=8&align=right'
	    ),
	    new TableColumn(
		'smstomach',
		'Stomach',
		'width=8&align=right'
	    ),
	    new TableColumn(
		'smleftarm',
		'L. Arm',
		'width=8&align=right'
	    ),
	    new TableColumn(
		'smrightarm',
		'R. Arm',
		'width=7&align=right'
	    ),
	    new TableColumn(
		'smleftleg',
		'L. Leg',
		'width=7&align=right'
	    ),
	    new TableColumn(
		'smrightleg',
		'R. Leg',
		'width=7&align=right'
	    ),
	    new TableColumn(
		'smleft',
		'Left',
		'width=8&align=right&append=' . urlencode('%')
	    ),
	    new TableColumn(
		'smmiddle',
		'Middle',
		'width=8&align=right&append=' . urlencode('%')
	    ),
	    new TableColumn(
		'smright',
		'Right',
		'width=8&align=right&append=' . urlencode('%')
	    )
	),
	'smweapon',
	'smhits',
	'smweapon',
	true,
	9999,
	'weap_page',
	'weap_sort',
	'weap_sortorder',
	'weaponstats2'
    );

    $result = $db->query("
	SELECT
	    hlstats_Events_Statsme2.weapon AS smweapon,
	    SUM(hlstats_Events_Statsme2.head) AS smhead,
	    SUM(hlstats_Events_Statsme2.chest) AS smchest,
	    SUM(hlstats_Events_Statsme2.stomach) AS smstomach,
	    SUM(hlstats_Events_Statsme2.leftarm) AS smleftarm,
	    SUM(hlstats_Events_Statsme2.rightarm) AS smrightarm,
	    SUM(hlstats_Events_Statsme2.leftleg) AS smleftleg,
	    SUM(hlstats_Events_Statsme2.rightleg) AS smrightleg,
	    SUM(hlstats_Events_Statsme2.head)+SUM(hlstats_Events_Statsme2.chest)+SUM(hlstats_Events_Statsme2.stomach)+
	    SUM(hlstats_Events_Statsme2.leftarm)+SUM(hlstats_Events_Statsme2.rightarm)+SUM(hlstats_Events_Statsme2.leftleg)+
	    SUM(hlstats_Events_Statsme2.rightleg) as smhits,							
	    IFNULL(ROUND((SUM(hlstats_Events_Statsme2.leftarm) + SUM(hlstats_Events_Statsme2.leftleg)) / (SUM(hlstats_Events_Statsme2.head) + SUM(hlstats_Events_Statsme2.chest) + SUM(hlstats_Events_Statsme2.stomach) + SUM(hlstats_Events_Statsme2.leftarm ) + SUM(hlstats_Events_Statsme2.rightarm) + SUM(hlstats_Events_Statsme2.leftleg) + SUM(hlstats_Events_Statsme2.rightleg)) * 100, 1), 0.0) AS smleft,
	    IFNULL(ROUND((SUM(hlstats_Events_Statsme2.rightarm) + SUM(hlstats_Events_Statsme2.rightleg)) / (SUM(hlstats_Events_Statsme2.head) + SUM(hlstats_Events_Statsme2.chest) + SUM(hlstats_Events_Statsme2.stomach) + SUM(hlstats_Events_Statsme2.leftarm ) + SUM(hlstats_Events_Statsme2.rightarm) + SUM(hlstats_Events_Statsme2.leftleg) + SUM(hlstats_Events_Statsme2.rightleg)) * 100, 1), 0.0) AS smright,
	    IFNULL(ROUND((SUM(hlstats_Events_Statsme2.head) + SUM(hlstats_Events_Statsme2.chest) + SUM(hlstats_Events_Statsme2.stomach)) / (SUM(hlstats_Events_Statsme2.head) + SUM(hlstats_Events_Statsme2.chest) + SUM(hlstats_Events_Statsme2.stomach) + SUM(hlstats_Events_Statsme2.leftarm ) + SUM(hlstats_Events_Statsme2.rightarm) + SUM(hlstats_Events_Statsme2.leftleg) + SUM(hlstats_Events_Statsme2.rightleg)) * 100, 1), 0.0) AS smmiddle
	FROM
	    hlstats_Events_Statsme2
	LEFT JOIN hlstats_Servers ON
	    hlstats_Servers.serverId=hlstats_Events_Statsme2.serverId
	WHERE
	    hlstats_Servers.game='$game_esc' AND hlstats_Events_Statsme2.PlayerId=$player
	GROUP BY
	    hlstats_Events_Statsme2.weapon
	HAVING
	    smhits > 0				
	ORDER BY
	    $tblWeaponstats2->sort $tblWeaponstats2->sortorder,
	    $tblWeaponstats2->sort2 $tblWeaponstats2->sortorder
    ");	

if ($db->num_rows($result) != 0)
{
        // PHP 8 Fix: Use object method
	$tblWeaponstats2->draw($result, $db->num_rows($result), 100);
}        
    ?>