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

    // Country Details
    
    // PHP 8 Fix: Null coalescing and casting
    $flag_in = isset($_GET['flag']) ? $_GET['flag'] : '';
    $flag = valid_request((string)$flag_in, false);
    
    if (!$flag) {
        error('No country ID specified.');
    }
    
    // Security: Escape variables
    $flag_esc = $db->escape($flag);
    $game_esc = $db->escape($game);

    $SQL = "
	SELECT
	    hlstats_Countries.flag,
	    hlstats_Countries.name,
	    COUNT(hlstats_Players.playerId) AS nummembers,
	    SUM(hlstats_Players.kills) AS kills,
	    SUM(hlstats_Players.deaths) AS deaths,
	    SUM(hlstats_Players.connection_time) AS connection_time,
	    ROUND(AVG(hlstats_Players.skill)) AS avgskill,
	    IFNULL(SUM(hlstats_Players.kills) / SUM(hlstats_Players.deaths), '-') AS kpd,
	    TRUNCATE(AVG(activity), 2) as activity
	FROM
	    hlstats_Countries 
	INNER JOIN
	    hlstats_Players
	ON (
	    hlstats_Players.flag=hlstats_Countries.flag
	    )
	WHERE
	    hlstats_Players.game='$game_esc'
	    AND hlstats_Players.flag='$flag_esc'
	    AND hlstats_Players.hideranking = 0
	    AND activity >= 0
	GROUP BY
	    hlstats_Countries.flag
    ";
    
    $db->query($SQL);
    if ($db->num_rows() != 1)
	error("No such countryclan '$flag'.");
    
    $clandata = $db->fetch_array();
    $db->free_result();
    
    // PHP 8 Fix: Cast to string
    $raw_name = isset($clandata['name']) ? (string)$clandata['name'] : '';
    $cl_name = str_replace(' ', '&nbsp;', htmlspecialchars($raw_name, ENT_COMPAT));
    
    // PHP 8 Fix: Check if tag exists (countries might not have tags like clans)
    $raw_tag = isset($clandata['tag']) ? (string)$clandata['tag'] : '';
    $cl_tag  = str_replace(' ', '&nbsp;', htmlspecialchars($raw_tag, ENT_COMPAT));
    $cl_full = "$cl_tag $cl_name";
    
    $db->query("SELECT name FROM hlstats_Games WHERE code='$game_esc'");
    if ($db->num_rows() != 1)
    {
	$gamename = ucfirst($game);
    }
    else
    {
        // PHP 8 Fix: Replace list()
        $row = $db->fetch_row();
	$gamename = ($row) ? $row[0] : '';
    }	
    
    pageHeader(
	array($gamename, 'Country Details', $cl_full),
	array(
	    $gamename=>$g_options['scripturl'] . "?game=$game",
	    'Country Rankings'=>$g_options['scripturl'] . "?mode=countryclans&game=$game",
	    'Country Details'=>''
	),
	$clandata['name']
    );
?>

<div class="block">
    <?php printSectionTitle('Country Information'); ?>

    <div class="subblock">
	<div style="float:left;width:48.5%;">
	    <table class="data-table">
		<tr class="data-table-head">
		    <td colspan="3">Statistics Summary</td>
		</tr>
		<tr class="bg1">
		    <td>Country:</td>
		    <td colspan="2"><?php
                        // PHP 8 Fix: Use $clandata['name'] instead of undefined $playerdata
                        $country_lower = strtolower((string)$clandata['name']);
			echo '<img src="'.getFlag($clandata['flag']).'" alt="'.$country_lower.'" title="'.$country_lower.'" />&nbsp;'; 
			echo '<strong>' . htmlspecialchars((string)$clandata['name'], ENT_COMPAT) . '</strong>';
		    ?></td>
		</tr>
		<tr class="bg2">
		    <td style="width:45%;"><?php
			echo 'Activity:';
		    ?></td>
		    <td align="left" width="40%">
	                                <meter min="0" max="100" low="25" high="50" optimum="75" value="<?php
                                        echo (float)$clandata['activity'] ?>"></meter>
		    </td>
		    <td style="width:15%;"><?php
			echo sprintf('%0.2f', (float)$clandata['activity']).'%';
		    ?></td>
		</tr>
		<tr class="bg1">
		    <td>Members:</td>
		    <td colspan="2">
			<strong><?php echo number_format((int)$clandata['nummembers']); ?></strong>
			<em>active members</em>
		    </td>
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
                        $avg_kills = ($num_members > 0) ? ((int)$clandata['kills'] / $num_members) : 0;
			echo number_format($avg_kills);
		    ?></td>
		</tr>
		
		<tr class="bg1">
		    <td>Kills per Death:</td>
		    <td colspan="2"><?php
                        $deaths = (int)$clandata['deaths'];
			if ($deaths != 0)
			{
			    printf('<strong>' . '%0.2f', (int)$clandata['kills'] / $deaths) . '</strong>';
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
			    echo sprintf('%.2f', ((int)$clandata['kills'] / ($con_time / 60)));
			} else {
			    echo '-'; 
			}
		    ?></td>
		</tr>

		<tr class="bg1">
		    <td>Avg. Member Points:</td>
		    <td colspan="2"><?php
			echo '<strong>' . number_format((int)$clandata['avgskill']) . '</strong>';
		    ?></td>
		</tr>

		<tr class="bg2">
		    <td >Avg. Connection Time:</td>
		    <td  colspan="2"><?php
			if ($con_time > 0 && $num_members > 0) {
			    echo timestamp_to_str($con_time / $num_members);
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
	    </table>
	</div>
	<div style="float:right;width:48.5%;text-align:center;padding-top:50px;">
<?php
            $flag_img_name = strtolower((string)$flag);
	    if (file_exists(IMAGE_PATH.'/flags/'.$flag_img_name.'_large.png')) {
		echo '<img src="'.IMAGE_PATH.'/flags/'.$flag_img_name.'_large.png" style="border:0px;" alt="'.htmlspecialchars((string)$flag).'" />';
	    } else {
		echo '<img src="'.IMAGE_PATH.'/countryclanlogos/NA.png" style="border:0px;" alt="" />';
	    }
?>
	</div>
	<div style="clear:both;"></div>
    </div>
</div>

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
	'members'
    );

    // PHP 8 Fix: Ensure min activity is numeric
    $min_act = isset($g_options['MinActivity']) ? (int)$g_options['MinActivity'] : 28;
    
    // PHP 8 Fix: Prevent division by zero in query building
    $clan_kills = (int)$clandata['kills'];
    $clan_kills_sql = ($clan_kills > 0) ? $clan_kills : 1;

    $result = $db->query("
	SELECT
	    hlstats_Players.playerId,
	    hlstats_Players.lastName,
	    hlstats_Players.country,
	    hlstats_Players.flag,
	    hlstats_Players.skill,
	    hlstats_Players.mmrank,
	    hlstats_Players.connection_time,
	    hlstats_Players.kills,
	    hlstats_Players.deaths,
	    ROUND(hlstats_Players.kills / IF(hlstats_Players.deaths = 0, 1, hlstats_Players.deaths), 2) AS kpd,
	    ROUND(hlstats_Players.kills / $clan_kills_sql * 100, 2) AS percent,
	    IF($min_act > (UNIX_TIMESTAMP() - last_event), ((100/$min_act) * ($min_act - (UNIX_TIMESTAMP() - last_event))), -1) as activity
	FROM
	    hlstats_Players
	WHERE
	    flag='$flag_esc'
	    AND hlstats_Players.hideranking = 0
	    AND hlstats_Players.game='$game_esc'      
	GROUP BY
	    hlstats_Players.playerId
	HAVING
	    activity >= 0
	ORDER BY
	    $tblMembers->sort $tblMembers->sortorder,
	    $tblMembers->sort2 $tblMembers->sortorder,
	    lastName ASC
	LIMIT $tblMembers->startitem,$tblMembers->numperpage
    ");
    
    $resultCount = $db->query("
	SELECT
	    playerId,
	    IF($min_act > (UNIX_TIMESTAMP() - last_event), ((100/$min_act) * ($min_act - (UNIX_TIMESTAMP() - last_event))), -1) as activity
	FROM
	    hlstats_Players
	WHERE
	    flag='$flag_esc'
	    AND hlstats_Players.hideranking = 0
	    AND hlstats_Players.game='$game_esc'      
	GROUP BY
	    hlstats_Players.playerId
	HAVING
	    activity >= 0
    ");
    
    // PHP 8 Fix: Use object method
    $numitems = $db->num_rows($resultCount);
?>
<div class="block" style="padding-top:10px;">
<?php
    printSectionTitle('Members');
    $tblMembers->draw($result, $numitems, 95);
?></div>