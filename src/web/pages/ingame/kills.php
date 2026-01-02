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
    
    // Player Details
    
    // PHP 8 Fix: Null coalescing and type casting
    $player_in = isset($_GET['player']) ? $_GET['player'] : 0;
    $player = valid_request((int)$player_in, true);
    
    $uniqueid_in = isset($_GET['uniqueid']) ? $_GET['uniqueid'] : '';
    $uniqueid = valid_request((string)$uniqueid_in, false);
    
    $game_in = isset($_GET['game']) ? $_GET['game'] : '';
    $game = valid_request((string)$game_in, false);
    
    // Security: Escape variables
    $game_esc = $db->escape($game);
    $uniqueid_esc = $db->escape($uniqueid);
    
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
	    hlstats_Players.country,
	    hlstats_Players.flag,
	    hlstats_Players.clan,
	    hlstats_Players.fullName,
	    hlstats_Players.email,
	    hlstats_Players.homepage,
	    hlstats_Players.icq,
	    hlstats_Players.game,
	    hlstats_Players.skill,
	    hlstats_Players.kills,
	    hlstats_Players.deaths,
	    IFNULL(kills/deaths, '-') AS kpd,
	    hlstats_Players.suicides,
	    hlstats_Players.headshots,
	    IFNULL(headshots/kills, '-') AS hpk,
	    hlstats_Players.shots,
	    hlstats_Players.hits,
	    IFNULL(ROUND((hits / shots * 100), 1), 0.0) AS acc,
	    CONCAT(hlstats_Clans.tag, ' ', hlstats_Clans.name) AS clan_name,
	    activity
	FROM
	    hlstats_Players
	LEFT JOIN hlstats_Clans ON
	    hlstats_Clans.clanId = hlstats_Players.clan
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
    if ($db->num_rows() != 1)
	$gamename = ucfirst($game);
    else {
        // PHP 8 Fix: Replace list()
        $row = $db->fetch_row();
	$gamename = $row[0];
    }
    
    // Added: Page Header for proper layout
    pageHeader(
	array ($gamename, 'Player Kills', $pl_name),
	array ($gamename=>"%s?game=$game", 'Player Kills'=>'')
    );

    $tblPlayerKillStats = new Table(
	array(
	    new TableColumn(
		'name',
		'Victim',
		'width=32&flag=1&link=' . urlencode('mode=statsme&player=%k')
	    ),
	    new TableColumn(
		'kills',
		'Kills',
		'width=8&align=right'
	    ),
	    new TableColumn(
		'deaths',
		'Deaths',
		'width=8&align=right'
	    ),
	    new TableColumn(
		'kpd',
		'Kpd',
		'width=12&align=right'
	    ),
	    new TableColumn(
		'headshots',
		'Headshots',
		'width=8&align=right'
	    ),
	    new TableColumn(
		'hpercent',
		'Perc. Headshots',
		'width=17&sort=no&type=bargraph'
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
	'victimId',
	'kills',
	'deaths',
	true,
	9999,
	'playerkills_page',
	'playerkills_sort',
	'playerkills_sortorder',
	'playerkills'
    );

     if(!isset($_GET['killLimit'])) {
	 $killLimit = 5;
     } else {
	 $killLimit = valid_request((int)$_GET['killLimit'], 1);
     }

    //there might be a better way to do this, but I could not figure one out.

    $db->query("DROP TABLE IF EXISTS hlstats_Frags_Kills");

    $sql_create_temp_table = "
	CREATE TEMPORARY TABLE hlstats_Frags_Kills
	(
	    playerId INT(10),
	    kills INT(10),
	    deaths INT(10),
	    headshot INT(10)
	) DEFAULT CHARSET=" . DB_CHARSET . " DEFAULT COLLATE=" . DB_COLLATE . ";
    ";

    $db->query($sql_create_temp_table);

    $db->query("
	INSERT INTO
	    hlstats_Frags_Kills
	    (
		playerId,
		kills,
		headshot		
	    )
	    SELECT
		victimId,
		killerId,
		headshot
	    FROM
		hlstats_Events_Frags
	    LEFT JOIN hlstats_Servers ON
		hlstats_Servers.serverId=hlstats_Events_Frags.serverId
	    WHERE
		hlstats_Servers.game='$game_esc' AND killerId = $player
	    GROUP BY
		hlstats_Events_Frags.id
    ");

    $db->query("
	INSERT INTO
	    hlstats_Frags_Kills
	    (
		playerId,
		deaths
	    )
	    SELECT
		killerId,
		victimId
	    FROM
		hlstats_Events_Frags
	    LEFT JOIN hlstats_Servers ON
		hlstats_Servers.serverId=hlstats_Events_Frags.serverId
	    WHERE
		hlstats_Servers.game='$game_esc' AND victimId = $player
    ");
	
    $result = $db->query("
	    SELECT
		SUM(hlstats_Frags_Kills.headshot) as headshots
	    FROM
		hlstats_Frags_Kills
	    GROUP BY
		hlstats_Frags_Kills.playerId
	    HAVING
		COUNT(hlstats_Frags_Kills.kills) >= $killLimit
    ");

    $realheadshots = 0;
    while ($rowdata = $db->fetch_array($result))  {
	$realheadshots += $rowdata['headshots'];
    }	
    
    // Prevent division by zero
    $realheadshots_sql = ($realheadshots > 0) ? $realheadshots : 1;

    $result = $db->query("
	    SELECT
		hlstats_Players.lastName AS name,
		hlstats_Players.flag AS flag,
		hlstats_Players.country AS country,
		Count(hlstats_Frags_Kills.kills) AS kills,
		Count(hlstats_Frags_Kills.deaths) AS deaths,
		hlstats_Frags_Kills.playerId as victimId,
		IFNULL(Count(hlstats_Frags_Kills.kills)/Count(hlstats_Frags_Kills.deaths),
		IFNULL(FORMAT(Count(hlstats_Frags_Kills.kills), 2), '-')) AS kpd,
		SUM(hlstats_Frags_Kills.headshot=1) AS headshots,
		IFNULL(SUM(hlstats_Frags_Kills.headshot=1) / Count(hlstats_Frags_Kills.kills), '-') AS hpk,
		ROUND(SUM(hlstats_Frags_Kills.headshot=1) / $realheadshots_sql * 100, 2) AS hpercent
	    FROM
		hlstats_Frags_Kills,
		hlstats_Players
	    WHERE
		hlstats_Frags_Kills.playerId = hlstats_Players.playerId
	    GROUP BY
		hlstats_Frags_Kills.playerId
	    HAVING
		Count(hlstats_Frags_Kills.kills) >= $killLimit
	    ORDER BY
		$tblPlayerKillStats->sort $tblPlayerKillStats->sortorder,
		$tblPlayerKillStats->sort2 $tblPlayerKillStats->sortorder
	    LIMIT 0,15 
    ");

    $numitems = $db->num_rows($result);
	
    if ($numitems > 0)
    {
?>
<div class="block">
    <?php printSectionTitle('Player Kill Statistics'); ?>
    <div class="subblock">
	<?php $tblPlayerKillStats->draw($result, $numitems, 100); ?>
    </div>
</div>
<?php
    }
?>