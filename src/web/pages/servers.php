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

    require_once('livestats.php');

    // Initialize variables
    $game = isset($game) ? (string)$game : '';
    $game_esc = $db->escape($game);
    $game_url = urlencode($game);

    $db->query("SELECT name FROM hlstats_Games WHERE code = '$game_esc'");
    if ($db->num_rows() < 1) {
        error("No such game '" . htmlspecialchars($game, ENT_QUOTES, 'UTF-8') . "'.");
    }

    $row = $db->fetch_row();
    $gamename = ($row) ? (string)$row[0] : ucfirst($game);
    $db->free_result();

    pageHeader(
        array($gamename, 'Server Live View'),
        array($gamename => "%s?game=$game_url", 'Server Live View' => '')
    );

?>

<br />
<?php
    $server_id = isset($_GET['server_id']) ? (int)$_GET['server_id'] : 0;

    if ($server_id <= 0) {
        error("Invalid server ID provided.", 0);
        pageFooter();
        die();
    }

    $query = "
        SELECT
            serverId,
            name,
            IF(publicaddress != '',
                publicaddress,
                CONCAT(address, ':', port)
            ) AS addr,
            statusurl,
            kills,
            players,
            rounds,
            suicides,
            headshots,
            bombs_planted,
            bombs_defused,
            ct_wins,
            ts_wins,
            ct_shots,
            ct_hits,
            ts_shots,
            ts_hits,
            act_players,
            max_players,
            act_map,
            map_started,
            map_ct_wins,
            map_ts_wins,
            game
        FROM
            hlstats_Servers
        WHERE
            serverId = $server_id
    ";

    $result = $db->query($query);
    $servers = array();
    $server_data = $db->fetch_array($result);
    if ($server_data) {
        $servers[] = $server_data;
    } else {
        error("No server found with ID '$server_id'.");
    }

    $graphbg_load  = htmlspecialchars((string)($g_options['graphbg_load'] ?? '282828'), ENT_QUOTES, 'UTF-8');
    $graphtxt_load = htmlspecialchars((string)($g_options['graphtxt_load'] ?? 'FFFFFF'), ENT_QUOTES, 'UTF-8');
?>

<div class="block">
<?php
    printSectionTitle('Server Live View');
    for ($i = 0; $i < count($servers); $i++)
    {
        $rowdata = $servers[$i];

        $server_id = (int)$rowdata['serverId'];
        $game = (string)$rowdata['game'];
        $game_url = urlencode($game);

        $addr = (string)$rowdata['addr'];
        $kills = (int)$rowdata['kills'];
        $headshots = (int)$rowdata['headshots'];
        $player_string = (int)$rowdata['act_players'] . "/" . (int)$rowdata['max_players'];
        $map_teama_wins = $rowdata['map_ct_wins'];
        $map_teamb_wins = $rowdata['map_ts_wins'];
?>
    <div class="subblock">
        <table class="data-table">
            <tr class="data-table-head">
                <td class="fSmall" style="width:37%;">&nbsp;Server</td>
                <td class="fSmall" style="width:23%;">&nbsp;Address</td>
                <td class="fSmall" style="width:6%;text-align:center;">&nbsp;Map</td>
                <td class="fSmall" style="width:6%;text-align:center;">&nbsp;Played</td>
                <td class="fSmall" style="width:10%;text-align:center;">&nbsp;Players</td>
                <td class="fSmall" style="width:6%;text-align:center;">&nbsp;Kills</td>
                <td class="fSmall" style="width:6%;text-align:center;">&nbsp;Headshots</td>
                <td class="fSmall" style="width:6%;text-align:center;">&nbsp;Hpk</td>
            </tr>
            <tr class="game-table-row">
                <td class="game-table-cell"><?php
        $image = getImage("/games/$game_url/game");
        $img_src = ($image && !empty($image['url'])) ? htmlspecialchars((string)$image['url'], ENT_QUOTES, 'UTF-8') : IMAGE_PATH . '/game.gif';
        echo '<img style="vertical-align:middle; margin-right:4px;" src="' . $img_src . '" alt="' . htmlspecialchars($game, ENT_QUOTES, 'UTF-8') . '" />&nbsp;';
        echo '<b>' . htmlspecialchars((string)$rowdata['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>';
                        ?></td>
            <td class="game-table-cell"><?php
        $safe_addr = htmlspecialchars((string)$addr, ENT_QUOTES, 'UTF-8');
        echo "{$safe_addr} <a href=\"steam://connect/{$safe_addr}\" style=\"color:black;\">(Join)</a>";
                    ?></td>
            <td class="game-table-cell" style="text-align:center;"><?php
        echo htmlspecialchars((string)($rowdata['act_map'] ?? ''), ENT_QUOTES, 'UTF-8');
                    ?></td>
            <td class="game-table-cell" style="text-align:center;"><?php
        $map_started = (int)($rowdata['map_started'] ?? 0);
        $stamp = ($map_started === 0) ? 0 : max(0, time() - $map_started);
        $hours = sprintf("%02d", floor($stamp / 3600));
        $min   = sprintf("%02d", floor(($stamp % 3600) / 60));
        $sec   = sprintf("%02d", floor($stamp % 60));
        echo "{$hours}:{$min}:{$sec}";
                    ?></td>
            <td class="game-table-cell" style="text-align:center;"><?php
        echo htmlspecialchars($player_string, ENT_QUOTES, 'UTF-8');
                    ?></td>
            <td class="game-table-cell" style="text-align:center;"><?php
        echo number_format($kills);
                    ?></td>
            <td class="game-table-cell" style="text-align:center;"><?php
        echo number_format($headshots);
                    ?></td>
            <td class="game-table-cell" style="text-align:center;"><?php
        if ($kills > 0)
            echo sprintf("%.4f", ($headshots / $kills));
        else
            echo sprintf("%.4f", 0);
                    ?></td>
        </tr>
    </table>
<?php
        printserverstats($server_id);
    }  //for servers
?>      </div>
</div>
<div class="block">
    <?php printSectionTitle('Server Load History'); ?>
    <div class="subblock">
        <table class="data-table">
            <tr class="data-table-head">
                <td class="fSmall">&nbsp;24h View</td>
            </tr>
            <tr class="data-table-row">
                <td style="text-align:center; height: 200px; vertical-align:middle;">
                    <img src="show_graph.php?type=0&amp;game=<?php echo $game_url; ?>&amp;width=870&amp;height=200&amp;server_id=<?php echo (int)$server_id; ?>&amp;bgcolor=<?php echo $graphbg_load; ?>&amp;color=<?php echo $graphtxt_load; ?>&amp;range=1" alt="24h View" />
                </td>
            </tr>
        </table>
        <br /><br />
        <table class="data-table">
            <tr class="data-table-head">
                <td class="fSmall">&nbsp;Last Week</td>
            </tr>
            <tr class="data-table-row">
                <td style="text-align:center; height: 200px; vertical-align:middle;">
                    <img src="show_graph.php?type=0&amp;game=<?php echo $game_url; ?>&amp;width=870&amp;height=200&amp;server_id=<?php echo (int)$server_id; ?>&amp;bgcolor=<?php echo $graphbg_load; ?>&amp;color=<?php echo $graphtxt_load; ?>&amp;range=2" alt="Last Week" />
                </td>
            </tr>
        </table>
        <br /><br />
        <table class="data-table">
            <tr class="data-table-head">
                <td class="fSmall">&nbsp;Last Month</td>
            </tr>
            <tr class="data-table-row">
                <td style="text-align:center; height: 200px; vertical-align:middle;">
                    <img src="show_graph.php?type=0&amp;game=<?php echo $game_url; ?>&amp;width=870&amp;height=200&amp;server_id=<?php echo (int)$server_id; ?>&amp;bgcolor=<?php echo $graphbg_load; ?>&amp;color=<?php echo $graphtxt_load; ?>&amp;range=3" alt="Last Month" />
                </td>
            </tr>
        </table>
        <br /><br />
        <table class="data-table">
            <tr class="data-table-head">
                <td class="fSmall">&nbsp;Last Year</td>
            </tr>
            <tr class="data-table-row">
                <td style="text-align:center; height: 200px; vertical-align:middle;">
                    <img src="show_graph.php?type=0&amp;game=<?php echo $game_url; ?>&amp;width=870&amp;height=200&amp;server_id=<?php echo (int)$server_id; ?>&amp;bgcolor=<?php echo $graphbg_load; ?>&amp;color=<?php echo $graphtxt_load; ?>&amp;range=4" alt="Last Year" />
                </td>
            </tr>
        </table>
    </div>
</div>