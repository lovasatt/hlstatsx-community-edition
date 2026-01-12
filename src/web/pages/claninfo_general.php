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

    global $db, $game, $g_options, $clan, $clandata, $totalclanplayers;

    // Security: Escape game variable
    $game_esc = $db->escape($game);

printSectionTitle('Clan Information');
?>
<div class="subblock">
    <div style="float:left;vertical-align:top;width:48.5%;">
	<table class="data-table">
	
	    <tr class="data-table-head">
		<td colspan="3">Statistics Summary</td>
	    </tr>
	    
	    <tr class="bg1">
		<td>Clan:</td>
		<td colspan="2"><strong><?php echo htmlspecialchars((string)$clandata['name']); ?></strong></td>
	    </tr>

	    <tr class="bg2">
		<td>Home Page:</td>
		<td colspan="2"><?php
		    if ($url = getLink((string)$clandata['homepage']))
		    {
			echo $url;
		    }
		    else
		    {
			echo '(Not specified.)';
		    }
		?></td>
	    </tr>

	    <tr class="bg1">
		<td style="width:45%;">Activity:</td>
		<td style="width:40%;">
		<meter min="0" max="100" low="25" high="50" optimum="75" value="<?php
		    echo (float)$clandata['activity'] ?>"></meter>
		</td>
		<td style="width:15%"><?php
		    echo sprintf('%0.2f', (float)$clandata['activity']).'%';
		?></td>
	    </tr>

	    <tr class="bg2">
		<td>Members:</td>
		<td colspan="2"><?php
		    echo (int)$clandata['nummembers'].
		    " active members (".(int)$totalclanplayers." total)"; 
		?></td>
	    </tr>

	    <tr class="bg1">
		<td>Avg. Member Points:</td>
		<td colspan="2"><strong><?php
		    echo number_format((int)$clandata['avgskill']);
		?></strong></td>
	    </tr>

	    <tr class="bg2">
		<td>Total Kills:</td>
		<td colspan="2"><?php
		    echo number_format((int)$clandata['kills']);
		?></td>
	    </tr>
		
	    <tr class="bg1">
		<td>Total Deaths:</td>
		<td colspan="2"><?php
		    echo number_format((int)$clandata['deaths']);
		?></td>
	    </tr>
            
	    <tr class="bg2">
		<td>Avg. Kills:</td>
		<td colspan="2"><?php
                    // PHP 8 Fix: Division by zero protection
                    $num_members = (int)$clandata['nummembers'];
                    $avg_kills = ($num_members > 0) ? ($clandata['kills'] / $num_members) : 0;
		    echo number_format($avg_kills);
		?></td>
	    </tr>
		
	    <tr class="bg1">
		<td>Kills per Death:</td>
		<td colspan="2"><?php
                    $deaths = (int)$clandata['deaths'];
		    if ($deaths != 0)
		    {
			echo sprintf('<strong>%0.2f</strong>', (int)$clandata['kills'] / $deaths);
		    }
		    else
		    {
			echo '-';
		    }
		?></td>
	    </tr>
        
	    <tr class="bg2">
		<td style="width:45%;">Kills per Minute:</td>
		<td colspan="2" style="width:55%;"><?php
                    $con_time = (int)$clandata['connection_time'];
		    if ($con_time > 0) {
			echo sprintf("%.2f", ((int)$clandata['kills'] / ($con_time / 60)));
		    } else {
			echo '-'; 
		    }
		?></td>
	    </tr>

	    <tr class="bg1">
		<td>Total Connection Time:</td>
		<td colspan="2"><?php
		    echo timestamp_to_str($con_time);
		?></td>
	    </tr>

	    <tr class="bg2">
		<td>Avg. Connection Time:</td>
		<td colspan="2"><?php
		    if ($con_time > 0 && $num_members > 0) {
			echo timestamp_to_str($con_time / $num_members);
		    } else {
			echo '-'; 
		    }
		?></td>
            </tr>

	    <tr class="bg1">
		<td>Favorite Server:*</td>
		<td colspan="2"><?php
		    $db->query("
			SELECT
			    hlstats_Events_Entries.serverId,
			    hlstats_Servers.name,
			    COUNT(hlstats_Events_Entries.serverId) AS cnt
			FROM
			    hlstats_Events_Entries
			INNER JOIN
			    hlstats_Servers
			ON
			    hlstats_Servers.serverId=hlstats_Events_Entries.serverId
			INNER JOIN 
			    hlstats_Players
			ON
			    (hlstats_Events_Entries.playerId=hlstats_Players.playerId)   
			WHERE   
			    clan=$clan
			GROUP BY
			    hlstats_Events_Entries.serverId
			ORDER BY
			    cnt DESC
			LIMIT 1  	
		    ");
			    
                    // PHP 8 Fix: Replace list()
                    $row = $db->fetch_row();
                    if ($row) {
			$favServerId = $row[0];
                        $favServerName = htmlspecialchars($row[1]);
			echo "<a href='hlstats.php?game=$game&amp;mode=servers&amp;server_id=$favServerId'> $favServerName </a>";
                    } else {
                        echo '-';
                    }
		?></td>
	    </tr>

            <tr class="bg2">
		<td>Favorite Map:*</td>
		<td colspan="2"><?php
		    $db->query("
			SELECT
			    hlstats_Events_Entries.map,
			    COUNT(map) AS cnt
			FROM
			    hlstats_Events_Entries
			INNER JOIN 
			    hlstats_Players
			ON
			    (hlstats_Events_Entries.playerId=hlstats_Players.playerId)   
			WHERE   
			    clan=$clan
			GROUP BY
			    hlstats_Events_Entries.map
			ORDER BY
			    cnt DESC
			LIMIT 1  	
		    ");

		    // PHP 8 Fix: Replace list()
                    $row = $db->fetch_row();
                    $favMap = ($row) ? htmlspecialchars($row[0]) : '';
                    if ($favMap) {
		        echo "<a href='hlstats.php?game=$game&amp;mode=mapinfo&amp;map=$favMap'> $favMap </a>";
                    } else {
                        echo '-';
                    }
		?></td>
	    </tr>

            <tr class="bg1">
                <td>Favorite Weapon:*</td>
                <td colspan="2"><?php
		    $result = $db->query("
			SELECT
			    hlstats_Events_Frags.weapon,
			    hlstats_Weapons.name,
			    COUNT(hlstats_Events_Frags.weapon) AS kills,
			    SUM(hlstats_Events_Frags.headshot=1) as headshots
			FROM
			    hlstats_Events_Frags
			INNER JOIN
			    hlstats_Weapons
			ON
			    hlstats_Weapons.code = hlstats_Events_Frags.weapon
			INNER JOIN 
                            hlstats_Players
			ON
			    hlstats_Events_Frags.killerId=hlstats_Players.playerId
			WHERE
			    clan=$clan
			AND
			    hlstats_Weapons.game='$game_esc'
			GROUP BY
			    hlstats_Events_Frags.weapon
			ORDER BY
			    kills desc, headshots desc
			LIMIT 1
                    ");

		    $weap_name = "";
		    $fav_weapon = "";

		    while ($rowdata = $db->fetch_row($result))
		    { 
			$fav_weapon = $rowdata[0];
			$weap_name = htmlspecialchars((string)$rowdata[1]);
		    }

		    if ($fav_weapon == '')
			$fav_weapon = 'Unknown';
                        
		    $image = getImage("/games/$game/weapons/$fav_weapon");
                    // check if image exists
                    $game_url = urlencode($game);
		    $weaponlink = "<a href=\"hlstats.php?mode=weaponinfo&amp;weapon=$fav_weapon&amp;game=$game_url\">";

                    if ($image) {
			$cellbody = "$weaponlink<img src=\"" . $image['url'] . "\" alt=\"$weap_name\" title=\"$weap_name\" />";
                    } else {
			$cellbody = "$weaponlink<strong> $weaponlink$weap_name</strong>";
                    }

		    $cellbody .= "</a>";

                    echo $cellbody;
               ?></td>
            </tr>
	</table>
    </div>
    <div style="float:right;vertical-align:top;width:48.5%;">
	<table class="data-table">
	    <tr class="data-table-head">
		<td colspan="3">Player Locations</td>
	    </tr>
	    <tr class="bg1">
		<td>
		    <div id="map" style="margin:10px auto;width: 430px; height: 290px;"></div>
		</td>
	    </tr>
	</table>
    </div>
</div><br />

<?php
    flush();
    
    $tblMembers = new Table(
	array(
	    new TableColumn(
		'lastName',
		'Name',
		'width=28&flag=1&link=' . urlencode('mode=playerinfo&amp;player=%k')
	    ),
                        new TableColumn(
                                'mmrank',
                                'Rank',
                                'width=4&type=elorank'
                        ),
	    new TableColumn(
		'skill',
		'Points',
		'width=6&align=right'
	    ),
	    new TableColumn(
		'activity',
		'Activity',
		'width=10&sort=no&type=bargraph'
	    ),
	    new TableColumn(
		'connection_time',
		'Time',
		'width=13&align=right&type=timestamp'
	    ),
	    new TableColumn(
		'kills',
		'Kills',
		'width=6&align=right'
	    ),
	    new TableColumn(
		'percent',
		'Clan Kills',
		'width=10&sort=no&type=bargraph'
	    ),
	    new TableColumn(
		'percent',
		'%',
		'width=6&sort=no&align=right&append=' . urlencode('%')
	    ),
	    new TableColumn(
		'deaths',
		'Deaths',
		'width=6&align=right'
	    ),
	    new TableColumn(
		'kpd',
		'Kpd',
		'width=6&align=right'
	    ),
	),
	'playerId',
	'skill',
	'kpd',
	true,
	20,
	'members_page',
	'members_sort',
	'members_sortorder',
	'members',
	'desc',
	true
    );

    // Prevent SQL error with division by zero
    $clan_kills_safe = ($clandata['kills'] == 0) ? 1 : $clandata['kills'];

    $result = $db->query("
	SELECT
	    playerId,
	    lastName,
	    country,
	    flag,
	    skill,
	    mmrank,
	    connection_time,
	    kills,
	    deaths,
	    ROUND(hlstats_Players.kills / IF(hlstats_Players.deaths = 0, 1, hlstats_Players.deaths), 2) AS kpd,
	    ROUND(hlstats_Players.kills / " . $clan_kills_safe . " * 100, 2) AS percent,
	    activity
	FROM
	    hlstats_Players
	WHERE
	    clan=$clan
	    AND hlstats_Players.hideranking = 0
	GROUP BY
	    hlstats_Players.playerId
	ORDER BY
	    $tblMembers->sort $tblMembers->sortorder,
	    $tblMembers->sort2 $tblMembers->sortorder,
	    hlstats_Players.skill DESC
	LIMIT $tblMembers->startitem,$tblMembers->numperpage
    ");
    
    $resultCount = $db->query("
	SELECT
	    COUNT(*)
	FROM
	    hlstats_Players
	WHERE
	    clan=$clan
	    AND hlstats_Players.hideranking = 0
    ");
    
    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row($resultCount);
    $numitems = ($row) ? (int)$row[0] : 0;
?>

<div style="clear:both;padding-top:20px;"></div>
<?php 
    printSectionTitle('Members');
    $tblMembers->draw($result, $numitems, 95);
?>
<br /><br />
<?php
    // PHP 8 Fix: Check if key exists
    if ( isset($g_options['show_google_map']) && $g_options['show_google_map'] == 1 ) {
	include(INCLUDE_PATH . '/google_maps.php');
	printMap('clan');
    }
?>