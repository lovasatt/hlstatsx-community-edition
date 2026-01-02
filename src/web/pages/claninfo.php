<?php
/*
 HLstatsX Community Edition - Real-time player and clan rankings and statistics
 Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
 http://www.hlxcommunity.com

 HLstatsX Community Edition is a continuation of 
 ELstatsNEO - Real-time player and clan rankings and statistics
 http://ovrsized.neo-soft.org/
 Copyleft (L) 2008-20XX Malte Bayer (steam@neo-soft.org)
 
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
 
 For support and installation notes visit http://ovrsized.neo-soft.org!
*/

    if (!defined('IN_HLSTATS')) {
	die('Do not access this file directly.');
    }

    global $db, $g_options;
    
    // Clan Details
    
    // PHP 8 Fix: Null coalescing and type casting
    $clan_in = isset($_GET['clan']) ? $_GET['clan'] : 0;
    $clan = valid_request((int)$clan_in, true);
    
    if (!$clan) {
        error("No clan ID specified.");
    }

    $db->query("
	SELECT
	    hlstats_Clans.tag,
	    hlstats_Clans.name,
	    hlstats_Clans.homepage,
	    hlstats_Clans.game,
	    hlstats_Clans.mapregion,
	    SUM(hlstats_Players.kills) AS kills,
	    SUM(hlstats_Players.deaths) AS deaths,
	    SUM(hlstats_Players.headshots) AS headshots,
	    SUM(hlstats_Players.connection_time) AS connection_time,
	    COUNT(hlstats_Players.playerId) AS nummembers,
	    ROUND(AVG(hlstats_Players.skill)) AS avgskill,
	    TRUNCATE(AVG(activity),2) as activity
	FROM
	    hlstats_Clans
	LEFT JOIN
	    hlstats_Players
	ON
	    hlstats_Players.clan = hlstats_Clans.clanId
	WHERE
	    hlstats_Clans.clanId=$clan
	    AND hlstats_Players.hideranking = 0
	GROUP BY
	    hlstats_Clans.clanId
    ");

    if ($db->num_rows() != 1) {
	error("No such clan '$clan'.");
    }
    
    $clandata = $db->fetch_array();

    // PHP 8 Fix: Ensure numeric types
    $kills = (int)($clandata['kills'] ?? 0);
    $headshots = (int)($clandata['headshots'] ?? 0);
    
    $realkills = ($kills == 0) ? 1 : $kills;
    $realheadshots = ($headshots == 0) ? 1 : $headshots;

    $db->query("
	SELECT
	    count(playerId)
	FROM
	    hlstats_Players
	WHERE
	    clan=$clan
	GROUP BY
	    clan
    ");

    // PHP 8 Fix: Replace list()
    $row = $db->fetch_array();
    $totalclanplayers = ($row) ? (int)$row[0] : 0;

    $db->free_result();
    
    // PHP 8 Fix: Cast to string for htmlspecialchars/preg_replace
    $raw_name = (string)($clandata['name'] ?? '');
    $raw_tag = (string)($clandata['tag'] ?? '');
    
    $cl_name = preg_replace('/\s/', '&nbsp;', htmlspecialchars($raw_name, ENT_COMPAT));
    $cl_tag  = preg_replace('/\s/', '&nbsp;', htmlspecialchars($raw_tag, ENT_COMPAT));
    $cl_full = "$cl_tag $cl_name";
    
    $game = isset($clandata['game']) ? $clandata['game'] : '';
    $game_esc = $db->escape($game);
    
    $db->query("SELECT name FROM hlstats_Games WHERE code='$game_esc'");

    if ($db->num_rows() != 1) {
	$gamename = ucfirst($game);
    } else {
        // PHP 8 Fix: Replace list()
        $row = $db->fetch_row();
	$gamename = ($row) ? $row[0] : '';
    }

    // Ajax Tabs Logic
    if (!empty($_GET['type']) && $_GET['type'] == 'ajax') {
        $tab_in = isset($_GET['tab']) ? (string)$_GET['tab'] : '';
        
        // PHP 8 Fix: Correct regex delimiters and allow pipe (|) and underscore (_)
	$tabs = explode('|', preg_replace('/[^a-z|_]/', '', strtolower($tab_in)));
	unset($_GET['type']);

	foreach ($tabs as $tab) {
            // Security limit
             $tab = preg_replace('/[^a-z0-9_]/', '', $tab);
	    if (file_exists(PAGE_PATH . '/claninfo_' . $tab . '.php')) {
		@include(PAGE_PATH . '/claninfo_' . $tab . '.php');
	    }
	}

	exit;
    }

    pageHeader(
	array($gamename, 'Clan Details', $cl_full),
	array(
	    $gamename=>$g_options['scripturl'] . "?game=$game",
	    'Clan Rankings'=>$g_options['scripturl'] . "?mode=clans&game=$game",
	    'Clan Details'=>''
	),
	$clandata['name'] ?? ''
    );

    if (isset($g_options['show_google_map']) && $g_options['show_google_map'] == 1) {
        $api_key = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';
	echo ('<script src="http://maps.google.com/maps/api/js?callback=Function.prototype&key=' . $api_key . '" type="text/javascript"></script>');
    }

    $mp_in = isset($_GET['members_page']) ? $_GET['members_page'] : '';
    $members_page = empty($mp_in) ? "Unknown" : valid_request($mp_in, true);
?>

<div class="block" id="main">

<?php
    // insert details pages here
    if (isset($g_options['playerinfo_tabs']) && $g_options['playerinfo_tabs'] == '1')
    {
?>
    <ul class="subsection_tabs" id="tabs_claninfo">
	<li>
	    <a href="#" id="tab_general">General</a>
	</li>
	<li>
	    <a href="#" id="tab_actions|teams">Teams &amp; Actions</a>
	</li>
	<li>
	    <a href="#" id="tab_weapons">Weapons</a>
	</li>
	<li>
	    <a href="#" id="tab_mapperformance">Maps</a>
	</li>
    </ul><br />
    <div id="main_content"></div>
    <script type="text/javascript">
    var Tabs = new Tabs($('main_content'), $$('#main ul.subsection_tabs a'), {
	'mode': 'claninfo',
	'game': '<?php echo htmlspecialchars($game); ?>',
	'loadingImage': '<?php echo IMAGE_PATH; ?>/ajax.gif',
	'defaultTab': 'general',
	'extra': {
            'clan': '<?php echo $clan; ?>',
            'members_page': '<?php echo htmlspecialchars((string)$members_page); ?>'
        }
    });
    </script>
<?php
    } else {
	echo "\n<div id=\"tabgeneral\">\n";
	require_once PAGE_PATH.'/claninfo_general.php';
	echo '</div>';
    
	echo "\n<div id=\"tabteams\">\n";
	require_once PAGE_PATH.'/claninfo_actions.php';
	require_once PAGE_PATH.'/claninfo_teams.php';
	echo '</div>';

	echo "\n<div id=\"tabweapons\">\n";
	require_once PAGE_PATH.'/claninfo_weapons.php';
	echo '</div>';
 
	echo "\n<div id=\"tabmaps\">\n";
	require_once PAGE_PATH.'/claninfo_mapperformance.php';
	echo '</div>';
    }
?>

<div class="block" style="clear:both;padding-top:12px;">
    <div class="subblock">
	<div style="float:left;">
	    Items marked "*" above are generated from the last <?php echo $g_options['DeleteDays']; ?> days.
	</div>
	<div style="float:right;">
	    <?php
		if (isset($_SESSION['loggedin']))
		{
		    echo 'Admin Options: <a href="'.$g_options['scripturl']."?mode=admin&amp;task=tools_editdetails_clan&amp;id=$clan\">Edit Clan Details</a><br />";
		}
	    ?>
	    Go to: <a href="<?php echo $g_options['scripturl'] . "?mode=players&amp;game=$game"; ?>">Clan Rankings</a>
	</div>
    </div>
</div>