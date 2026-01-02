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
    
    // Contents
    
    $resultGames = $db->query("
	SELECT
	    code,
	    name
	FROM
	    hlstats_Games
	WHERE
	    hidden='0'
	ORDER BY
	    realgame, name ASC
    ");
    
    $num_games = $db->num_rows($resultGames);
    $redirect_to_game = 0;
    
    // PHP 8 Fix: Ensure function exists before call, handle null coalescing
    $get_game = $_GET['game'] ?? null;
    $game = (!empty($get_game) && function_exists('valid_request')) ? valid_request($get_game, false) : null;

    if ($num_games == 1 || !empty($game)) {
	$redirect_to_game++;
	if ($num_games == 1) {
            // PHP 8 Fix: Avoid list() on potential false result
            $row = $db->fetch_row($resultGames);
            if ($row) {
	        $game = $row[0];
            }
	}
	
	include(PAGE_PATH . '/game.php');
    } else {
	unset($_SESSION['game']);
	
	pageHeader(array('Contents'), array('Contents' => ''));
	include(PAGE_PATH . '/voicecomm_serverlist.php');
	printSectionTitle('Games');
    ?>

	<div class="subblock">
	
	    <table class="data-table">
	    
		<tr class="data-table-head">
		    <td class="fSmall" width="60%" align="left">&nbsp;Game</td>
		    <td class="fSmall" width="10%" align="center">&nbsp;Players</td>
		    <td class="fSmall" width="20%" align="center">&nbsp;Top Player</td>
		    <td class="fSmall" width="10%" align="center">&nbsp;Top Clan</td>
		</tr>
		
<?php
        $nonhiddengamecodes = array();
        
	while ($gamedata = $db->fetch_row($resultGames))
	{
            $game_code = $gamedata[0];
            $game_name = $gamedata[1];
	    $nonhiddengamecodes[] = "'" . $db->escape($game_code) . "'";
            
	    $result = $db->query("
		SELECT
		    playerId,
		    lastName,
		    activity
		FROM
		    hlstats_Players
		WHERE
		    game='$game_code'
		    AND hideranking=0
		ORDER BY
		    ".$g_options['rankingtype']." DESC,
		    (kills/IF(deaths=0,1,deaths)) DESC
		LIMIT 1
	    ");
	
	    if ($db->num_rows($result) == 1)
	    {
		$topplayer = $db->fetch_row($result);
	    }
	    else
	    {
		$topplayer = false;
	    }
		    
	    $result = $db->query("
		SELECT
		    hlstats_Clans.clanId,
		    hlstats_Clans.name,
		    AVG(hlstats_Players.skill) AS skill,
		    AVG(hlstats_Players.kills) AS kills,
		    COUNT(hlstats_Players.playerId) AS numplayers
		FROM
		    hlstats_Clans
		LEFT JOIN
		    hlstats_Players
		ON
		    hlstats_Players.clan = hlstats_Clans.clanId
		WHERE
		    hlstats_Clans.game='$game_code'
		    AND hlstats_Clans.hidden = 0
		    AND hlstats_Players.hideranking=0
		GROUP BY
		    hlstats_Clans.clanId
		HAVING
		    ".$g_options['rankingtype']." IS NOT NULL
		    AND numplayers >= 3
		ORDER BY
		    ".$g_options['rankingtype']." DESC
		LIMIT 1
	    ");

	    if ($db->num_rows($result) == 1)
	    {
		$topclan = $db->fetch_row($result);
	    }
	    else
	    {
		$topclan = false;
	    }

	    $result= $db->query("
		SELECT
		    SUM(act_players) AS `act_players`,                                
		    SUM(max_players) AS `max_players`
		FROM
		    hlstats_Servers
		WHERE
		    hlstats_Servers.game='$game_code'
	    ");
			    
	    $numplayers = $db->fetch_array($result);
	    if ($numplayers && $numplayers['act_players'] == 0 && $numplayers['max_players'] == 0)
		$numplayers = false;
	    else if ($numplayers)
		$player_string = $numplayers['act_players'].'/'.$numplayers['max_players'];
            else 
                $numplayers = false;
?>				
		<tr class="game-table-row">
		    <td class="game-table-cell" style="height:30px">
			<div style="float:left;line-height:30px;" class="fHeading">&nbsp;<a href="<?php echo $g_options['scripturl'] . "?game=$game_code"; ?>"><img src="<?php
	    $image = getImage("/games/$game_code/game");
	    if ($image)
		echo $image['url'];
	    else
		echo IMAGE_PATH . '/game.gif';
               ?>"  style="margin-left: 3px; margin-right: 4px;" alt="Game" /></a><a href="<?php echo $g_options['scripturl'] . "?game=$game_code"; ?>"><?php echo $game_name; ?></a>
			</div>
			<div style="float:right;">
			    <div style="margin-left: 3px; margin-right: 4px; vertical-align:top; text-align:center;"><a href="<?php echo $g_options['scripturl'] . "?mode=clans&amp;game=$game_code"; ?>"><img src="<?php echo IMAGE_PATH; ?>/clan.gif" alt="Clan Rankings" /></a></div>
			    <div style="vertical-align:bottom; text-align:left;">&nbsp;<a href="<?php echo $g_options['scripturl'] . "?mode=clans&amp;game=$game_code"; ?>" class="fSmall">Clans</a>&nbsp;&nbsp;</div>
			</div>
			    
			<div style="float:right;">
			    <div style="margin-left: 3px; margin-right: 4px; vertical-align:top; text-align:center;"><a href="<?php echo $g_options['scripturl'] . "?mode=players&amp;game=$game_code"; ?>"><img src="<?php echo IMAGE_PATH; ?>/player.gif" alt="Player Rankings" /></a></div>
			    <div style="vertical-align:bottom; text-align:left;">&nbsp;<a href="<?php echo $g_options['scripturl'] . "?mode=players&amp;game=$game_code"; ?>" class="fSmall">Players</a>&nbsp;&nbsp;</div>
			</div>
		    </td>
		    <td class="game-table-cell" style="text-align:center;"><?php 
	    if ($numplayers)
	    {
		echo $player_string;
	    }
	    else
	    {
		echo '-';
	    }
		    ?>
		    </td>
		    <td class="game-table-cell" style="text-align:center;"><?php
	    if ($topplayer)
	    {
                // PHP 8 Fix: Cast to string for htmlspecialchars
		echo '<a href="' . $g_options['scripturl'] . '?mode=playerinfo&amp;player='
		    . $topplayer[0] . '">'.htmlspecialchars((string)$topplayer[1], ENT_COMPAT).'</a>';
	    }
	    else
	    {
		echo '-';
	    }
		    ?></td>
		    <td class="game-table-cell" style="text-align:center;"><?php
	    if ($topclan)
	    {
                // PHP 8 Fix: Cast to string for htmlspecialchars
		echo '<a href="' . $g_options['scripturl'] . '?mode=claninfo&amp;clan='
		    . $topclan[0] . '">'.htmlspecialchars((string)$topclan[1], ENT_COMPAT).'</a>';
	    }
	    else
	    {
		echo '-';
	    }
		    ?></td>
		</tr>
<?php
	}
?>	
		</table>
	
	</div><br /><br />
	<br />
	
<?php
	printSectionTitle('General Statistics');
	
	// Build safe string for IN clause
        $nonhiddengamestring = (!empty($nonhiddengamecodes)) ? '(' . implode(',', $nonhiddengamecodes) . ')' : "('')";
	
	$result = $db->query("SELECT COUNT(playerId) FROM hlstats_Players WHERE game IN $nonhiddengamestring");
        $row = $db->fetch_row($result);
	$num_players = number_format((int)($row[0] ?? 0));

	$result = $db->query("SELECT COUNT(clanId) FROM hlstats_Clans WHERE game IN $nonhiddengamestring");
	$row = $db->fetch_row($result);
	$num_clans = number_format((int)($row[0] ?? 0));

	$result = $db->query("SELECT COUNT(serverId) FROM hlstats_Servers WHERE game IN $nonhiddengamestring");
	$row = $db->fetch_row($result);
	$num_servers = number_format((int)($row[0] ?? 0));
	
	$result = $db->query("SELECT SUM(kills) FROM hlstats_Servers WHERE game IN $nonhiddengamestring");
	$row = $db->fetch_row($result);
	$num_kills = number_format((int)($row[0] ?? 0));

	$result = $db->query("
	    SELECT 
		eventTime
	    FROM
		hlstats_Events_Frags
	    ORDER BY
		id DESC
	    LIMIT 1
	");
	$row = $db->fetch_row($result);
        $lastevent = $row ? $row[0] : null;
?>

	<div class="subblock">
	
	    <ul>
		<li><?php
		    echo "<strong>$num_players</strong> players and <strong>$num_clans</strong> clans "
			. "ranked in <strong>$num_games</strong> games on <strong>$num_servers</strong>"
			. " servers with <strong>$num_kills</strong> kills."; ?></li>
<?php
	if ($lastevent)
	{
	    echo "\t\t\t\t<li>Last Kill <strong> " . date('g:i:s A, D. M. d, Y', strtotime($lastevent)) . "</strong></li>";
	}
?>
		<li>All statistics are generated in real-time. Event history data expires after <strong><?php echo $g_options['DeleteDays']; ?></strong> days.</li>
	    </ul>
	</div>
<?php
    }
?>