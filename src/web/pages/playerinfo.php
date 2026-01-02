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
    // PHP 8 Fix: Null coalescing and explicit casting
    $player_in = isset($_GET['player']) ? $_GET['player'] : '';
    $player = valid_request((int)$player_in, true);
    
    $uniqueid_in = isset($_GET['uniqueid']) ? $_GET['uniqueid'] : '';
    $uniqueid = valid_request((string)$uniqueid_in, false);
    
    $game_in = isset($_GET['game']) ? $_GET['game'] : '';
    $game = valid_request((string)$game_in, false);

    if (!$player && $uniqueid) {
	if (!$game) {
            $redirect_url = $g_options['scripturl'] . "&mode=search&st=uniqueid&q=" . urlencode($uniqueid);
	    header("Location: $redirect_url");
	    exit;
	}

	$uniqueid = preg_replace('/^STEAM_\d+?\:/i','',$uniqueid);
        
        // Security: Escape uniqueid
        $uniqueid_esc = $db->escape($uniqueid);

        $db->query("
	    SELECT
		hlstats_PlayerUniqueIds.playerId
	    FROM
		hlstats_PlayerUniqueIds
	    WHERE
		hlstats_PlayerUniqueIds.uniqueId = '$uniqueid_esc'
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
	error("No player ID specified.");
    }

    $db->query("
	SELECT
	    hlstats_Players.playerId,
	    hlstats_Players.connection_time,
	    unhex(replace(hex(hlstats_Players.lastName), 'E280AE', '')) as lastName,
	    hlstats_Players.country,
	    hlstats_Players.city,
	    hlstats_Players.flag,
	    hlstats_Players.clan,
	    hlstats_Players.fullName,
	    hlstats_Players.email,
	    hlstats_Players.homepage,
	    hlstats_Players.icq,
	    hlstats_Players.mmrank,
	    hlstats_Players.game,
	    hlstats_Players.hideranking,
	    hlstats_Players.blockavatar,
	    hlstats_Players.skill,
	    hlstats_Players.kills,
	    hlstats_Players.deaths,
	    IFNULL(kills / deaths, '-') AS kpd,
	    hlstats_Players.suicides,
	    hlstats_Players.headshots,
	    IFNULL(headshots / kills, '-') AS hpk,
	    hlstats_Players.shots,
	    hlstats_Players.hits,
	    hlstats_Players.teamkills,
	    IFNULL(ROUND((hits / shots * 100), 1), 0) AS acc,
	    CONCAT(hlstats_Clans.name) AS clan_name,
	    activity
	FROM
	    hlstats_Players
	LEFT JOIN
	    hlstats_Clans
	ON
	    hlstats_Clans.clanId = hlstats_Players.clan
	WHERE
	    hlstats_Players.playerId = $player
	LIMIT
	    1
    ");

    if ($db->num_rows() != 1) {
	error("No such player '$player'.");
    }

    $playerdata = $db->fetch_array();
    $db->free_result();
    $pl_name = $playerdata['lastName'];

    if (strlen($pl_name) > 10) {
	$pl_shortname = substr($pl_name, 0, 8) . '...';
    } else {
	$pl_shortname = $pl_name;
    }

    $pl_name = htmlspecialchars($pl_name, ENT_COMPAT);
    $pl_shortname = htmlspecialchars($pl_shortname, ENT_COMPAT);
    $pl_urlname = urlencode($playerdata['lastName']);
    $game = $playerdata['game'];
    
    // Security: Escape game variable
    $game_esc = $db->escape($game);

    $db->query("
	SELECT
	    hlstats_Games.name
	FROM
	    hlstats_Games
	WHERE
	    hlstats_Games.code = '$game_esc'
    ");

    if ($db->num_rows() != 1) {
	$gamename = ucfirst($game);
    } else {
        // PHP 8 Fix: Replace list()
        $row = $db->fetch_row();
        $gamename = ($row) ? $row[0] : ucfirst($game);
    }

    $hideranking = $playerdata['hideranking'];

    if ($hideranking == 2) {
	$statusmsg = '<span style="color:red;font-weight:bold;">Banned</span>';
    } else {
	$statusmsg = '<span style="color:green;font-weight:bold;">In good standing</span>';
    }
// Required on a few pages, just decided to add it here
// May get moved in the future

$db->query("
	SELECT
	    COUNT(hlstats_Events_Frags.killerId)
	FROM
	    hlstats_Events_Frags
	WHERE
	    hlstats_Events_Frags.killerId = $player
	    AND hlstats_Events_Frags.headshot = 1
    ");

    // PHP 8 Fix: Replace list() which causes Fatal Error on empty result
    $row = $db->fetch_row();
    $realheadshots = ($row) ? (int)$row[0] : 0;

    $db->query("
	SELECT
	    COUNT(hlstats_Events_Frags.killerId)
	FROM
	    hlstats_Events_Frags
	WHERE
	    hlstats_Events_Frags.killerId = $player
    ");

    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row();
    $realkills = ($row) ? (int)$row[0] : 0;

    $db->query("
	SELECT
	    COUNT(hlstats_Events_Frags.victimId)
	FROM
	    hlstats_Events_Frags
	WHERE
	    hlstats_Events_Frags.victimId = $player
    ");

    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row();
    $realdeaths = ($row) ? (int)$row[0] : 0;

    $db->query("
	SELECT
	    COUNT(hlstats_Events_Teamkills.killerId)
	FROM
	    hlstats_Events_Teamkills
	WHERE
	    hlstats_Events_Teamkills.killerId = $player
    ");

    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row();
    $realteamkills = ($row) ? (int)$row[0] : 0;

    if (!isset($_GET['killLimit'])) {
	$killLimit = 5;
    } else {
	$killLimit = valid_request((int)$_GET['killLimit'], true);
    }

    if (isset($_GET['type']) && $_GET['type'] == 'ajax') {
        $tab_input = isset($_GET['tab']) ? $_GET['tab'] : '';
        
        // PHP 8 Fix: Correct regex syntax AND Allow underscore (_)
        // JAVÍTÁS: Hozzáadtam a `_` karaktert az engedélyezettekhez ([^a-z_]), 
        // különben a "general_aliases" stringből "generalaliases" lett, és az explode nem működött.
	$tabs = explode('_', preg_replace('/[^a-z_]/', '', strtolower($tab_input)));

	foreach ($tabs as $tab) {
            // Security limit: file name parts should be alphanumeric
            $tab = preg_replace('/[^a-z0-9]/', '', $tab);
            
	    if (!empty($tab) && file_exists(PAGE_PATH . "/playerinfo_$tab.php")) {
		@include(PAGE_PATH . "/playerinfo_$tab.php");
	    }
	}

	exit;
    }

    pageHeader(
	array ($gamename, 'Player Details', $pl_name),
	array
	(
	    $gamename=>$g_options['scripturl'] . "?game=$game",
	    'Player Rankings'=>$g_options['scripturl'] . "?mode=players&game=$game",
	    'Player Details'=>""
	),
	$pl_name
    );
?>
<div class="block" id="main">
<?php	
    // PHP 8 Fix: Ensure array key exists
    if (isset($g_options['playerinfo_tabs']) && $g_options['playerinfo_tabs']=='1')
    {
?>
    <ul class="subsection_tabs" id="tabs_playerinfo">
	<li>
	    <a href="#" id="tab_general_aliases">General</a>
	</li>
	<li>
	    <a href="#" id="tab_playeractions_teams">Teams &amp; Actions</a>
	</li>
	<li>
	    <a href="#" id="tab_weapons">Weapons</a>
	</li>
	<li>
	    <a href="#" id="tab_mapperformance_servers">Maps &amp; Servers</a>
	</li>
	<li>
	    <a href="#" id="tab_killstats">Killstats</a>
	</li>
    </ul><br />
    <div id="main_content"></div>
    <script type="text/javascript">
	var Tabs = new Tabs
	(
	    $('main_content'), $$('#main ul.subsection_tabs a'),
	    {
		'mode': 'playerinfo',
		'game': '<?php echo htmlspecialchars($game); ?>',
		'loadingImage': '<?php echo IMAGE_PATH; ?>/ajax.gif',
		'defaultTab': 'general_aliases',
		'extra':
		{
		    'player': '<?php echo $player; ?>', 'killLimit': '<?php echo $killLimit; ?>'
		}
	    }
	);
    </script>
<?php
    }
    else
    {
	echo "\n<div id=\"tabgeneral\" class=\"tab\">\n";
	    require_once PAGE_PATH.'/playerinfo_general.php';
	    require_once PAGE_PATH.'/playerinfo_aliases.php';
	echo '</div>';
	echo "\n<div id=\"tabteams\" class=\"tab\">\n";
	    require_once PAGE_PATH.'/playerinfo_playeractions.php';
	    require_once PAGE_PATH.'/playerinfo_teams.php';
	echo '</div>';
	echo "\n<div id=\"tabweapons\" class=\"tab\">\n";
	    require_once PAGE_PATH.'/playerinfo_weapons.php';
	echo '</div>';
	echo "\n<div id=\"tabmaps\" class=\"tab\">\n";
	    require_once PAGE_PATH.'/playerinfo_mapperformance.php';
	    require_once PAGE_PATH.'/playerinfo_servers.php';
	echo '</div>';
	echo "\n<div id=\"tabkills\" class=\"tab\">\n";
	    require_once PAGE_PATH.'/playerinfo_killstats.php';
	echo '</div>';
    }
?>
</div>
<div class="block" style="clear:both;padding-top:12px;">
    <div class="subblock">
	<div style="float:left;">
	    Items marked "*" above are generated from the last <?php echo $g_options['DeleteDays']; ?> days.
	</div>
	<div style="float:right;">
	    <?php
		if (isset($_SESSION['loggedin']))
		{
		    echo 'Admin Options: <a href="'.$g_options['scripturl']."?mode=admin&amp;task=tools_editdetails_player&amp;id=$player\">Edit Player Details</a><br />";
		}
	    ?>
	    Go to: <a href="<?php echo $g_options['scripturl'] . "?mode=players&amp;game=$game"; ?>">Player Rankings</a>
	</div>
    </div>
</div>