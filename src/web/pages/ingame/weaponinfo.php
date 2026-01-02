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

    // Weapon Details
    
    // PHP 8 Fix: Null coalescing and casting
    $weapon_in = isset($_GET['weapon']) ? $_GET['weapon'] : '';
    $weapon = valid_request((string)$weapon_in, false);
    
    if (!$weapon) {
        error('No weapon ID specified.');
    }
    
    // Security: Escape variables
    $game_esc = $db->escape($game);
    $weapon_esc = $db->escape($weapon);
        
    $db->query("
	SELECT
	    name
	FROM
	    hlstats_Weapons
	WHERE
	    code='$weapon_esc'
	    AND game='$game_esc'
    ");
    
    if ($db->num_rows() != 1) {
	$wep_name = ucfirst($weapon);
    } else
    {
	$weapondata = $db->fetch_array();
	$db->free_result();
	$wep_name = $weapondata['name'];
    }
    
    $db->query("SELECT name FROM hlstats_Games WHERE code='$game_esc'");
    if ($db->num_rows() != 1) {
	error('Invalid or no game specified.');
    } else {
        // PHP 8 Fix: Replace list()
        $row = $db->fetch_row();
	$gamename = ($row) ? $row[0] : '';
    }

    $table = new Table(
	array(
	    new TableColumn(
		'killerName',
		'Player',
		'width=60&align=left&flag=1&link=' . urlencode('mode=statsme&amp;player=%k') 
	    ),
	    new TableColumn(
		'frags',
		ucfirst($weapon) . ' kills',
		'width=15&align=right'
	    ),
	    new TableColumn(
		'headshots',
		'Headshots',
		'width=15&align=right'
	    ),
	    new TableColumn(
		'hpk',
		'Hpk',
		'width=5&align=right'
	    ),
	),
	'killerId', // keycol
	'frags', // sort_default
	'killerName', // sort_default2
	true, // showranking
	25 // numperpage
    );
    
    // Logic Fix: Count total items for pagination (missing in original code)
    $db->query("
        SELECT
            COUNT(DISTINCT hlstats_Events_Frags.killerId)
        FROM
            hlstats_Events_Frags,
            hlstats_Players
        WHERE
            hlstats_Players.playerId = hlstats_Events_Frags.killerId
            AND hlstats_Events_Frags.weapon='$weapon_esc'
            AND hlstats_Players.game='$game_esc'
            AND hlstats_Players.hideranking<>'1'
    ");
    $row = $db->fetch_row();
    $numitems = ($row) ? (int)$row[0] : 0;

    $result = $db->query("
	SELECT
	    hlstats_Events_Frags.killerId,
	    hlstats_Players.country,
	    hlstats_Players.flag,
	    hlstats_Players.lastName AS killerName,
	    COUNT(hlstats_Events_Frags.weapon) AS frags,
	    SUM(hlstats_Events_Frags.headshot=1) as headshots,
	    IFNULL(SUM(hlstats_Events_Frags.headshot=1) / Count(hlstats_Events_Frags.weapon), '-') AS hpk
	FROM
	    hlstats_Events_Frags,
	    hlstats_Players
	WHERE
	    hlstats_Players.playerId = hlstats_Events_Frags.killerId
	    AND hlstats_Events_Frags.weapon='$weapon_esc'
	    AND hlstats_Players.game='$game_esc'
	    AND hlstats_Players.hideranking<>'1'
	GROUP BY
	    hlstats_Events_Frags.killerId
	ORDER BY
	    $table->sort $table->sortorder,
	    $table->sort2 $table->sortorder
	LIMIT $table->startitem,$table->numperpage
    ");

  // Fix: Use actual count for pagination
  $table->draw($result, $numitems, 100);
?>