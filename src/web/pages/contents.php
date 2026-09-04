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
                $game = (string)$row[0];
            }
        }
        $db->free_result($resultGames);

        include(PAGE_PATH . '/game.php');
    } else {
        unset($_SESSION['game']);

        $scripturl = htmlspecialchars((string)($g_options['scripturl'] ?? ''), ENT_QUOTES, 'UTF-8');
        $deletedays = (int)($g_options['DeleteDays'] ?? 28);
        $rankingtype = (isset($g_options['rankingtype']) && $g_options['rankingtype'] === 'kills') ? 'kills' : 'skill';

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
            $game_code_esc = $db->escape((string)$game_code);
            $game_code_url = urlencode((string)$game_code);

            $res_topplayer = $db->query("
                SELECT
                    playerId,
                    unhex(replace(hex(lastName), 'E280AE', '')) AS lastName,
                    activity
                FROM
                    hlstats_Players
                WHERE
                    game='$game_code_esc'
                    AND hideranking=0
                ORDER BY
                    $rankingtype DESC,
                    (kills/IF(deaths=0,1,deaths)) DESC
                LIMIT 1
            ");

            if ($db->num_rows($res_topplayer) == 1)
            {
                $topplayer = $db->fetch_row($res_topplayer);
            }
            else
            {
                $topplayer = false;
            }
            $db->free_result($res_topplayer);

            $res_topclan = $db->query("
                SELECT
                    hlstats_Clans.clanId,
                    unhex(replace(hex(hlstats_Clans.name), 'E280AE', '')) AS name,
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
                    hlstats_Clans.game='$game_code_esc'
                    AND hlstats_Clans.hidden = 0
                    AND hlstats_Players.hideranking=0
                GROUP BY
                    hlstats_Clans.clanId,
                    hlstats_Clans.name
                HAVING
                    $rankingtype IS NOT NULL
                    AND numplayers >= 3
                ORDER BY
                    $rankingtype DESC
                LIMIT 1
            ");

            if ($db->num_rows($res_topclan) == 1)
            {
                $topclan = $db->fetch_row($res_topclan);
            }
            else
            {
                $topclan = false;
            }
            $db->free_result($res_topclan);

            $res_srv_pl = $db->query("
                SELECT
                    SUM(act_players) AS `act_players`,
                    SUM(max_players) AS `max_players`
                FROM
                    hlstats_Servers
                WHERE
                    hlstats_Servers.game='$game_code_esc'
            ");

            $numplayers = $db->fetch_array($res_srv_pl);
            $db->free_result($res_srv_pl);
            if ($numplayers && $numplayers['act_players'] == 0 && $numplayers['max_players'] == 0)
                $numplayers = false;
            else if ($numplayers)
                $player_string = $numplayers['act_players'].'/'.$numplayers['max_players'];
            else
                $numplayers = false;
?>
                <tr class="game-table-row">
                    <td class="game-table-cell" style="height:30px">
                        <div style="float:left;line-height:30px;" class="fHeading">&nbsp;<a href="<?php echo $scripturl . "?game=$game_code_url"; ?>"><img src="<?php
            $image = getImage("/games/$game_code_url/game");
            if ($image && !empty($image['url']))
                echo htmlspecialchars((string)$image['url'], ENT_QUOTES, 'UTF-8');
            else
                echo IMAGE_PATH . '/game.gif';
               ?>"  style="margin-left: 3px; margin-right: 4px; vertical-align:middle;" alt="Game" /></a><a href="<?php echo $scripturl . "?game=$game_code_url"; ?>"><?php echo htmlspecialchars((string)$game_name, ENT_QUOTES, 'UTF-8'); ?></a>
                        </div>
                        <div style="float:right;">
                            <div style="margin-left: 3px; margin-right: 4px; vertical-align:top; text-align:center;"><a href="<?php echo $scripturl . "?mode=clans&amp;game=$game_code_url"; ?>"><img src="<?php echo IMAGE_PATH; ?>/clan.gif" alt="Clan Rankings" /></a></div>
                            <div style="vertical-align:bottom; text-align:left;">&nbsp;<a href="<?php echo $scripturl . "?mode=clans&amp;game=$game_code_url"; ?>" class="fSmall">Clans</a>&nbsp;&nbsp;</div>
                        </div>

                        <div style="float:right;">
                            <div style="margin-left: 3px; margin-right: 4px; vertical-align:top; text-align:center;"><a href="<?php echo $scripturl . "?mode=players&amp;game=$game_code_url"; ?>"><img src="<?php echo IMAGE_PATH; ?>/player.gif" alt="Player Rankings" /></a></div>
                            <div style="vertical-align:bottom; text-align:left;">&nbsp;<a href="<?php echo $scripturl . "?mode=players&amp;game=$game_code_url"; ?>" class="fSmall">Players</a>&nbsp;&nbsp;</div>
                        </div>
                    </td>
                    <td class="game-table-cell" style="text-align:center;"><?php
            if ($numplayers)
            {
                echo htmlspecialchars((string)$player_string, ENT_QUOTES, 'UTF-8');
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
                echo '<a href="' . $scripturl . '?mode=playerinfo&amp;player='
                    . (int)$topplayer[0] . '">'.htmlspecialchars((string)$topplayer[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</a>';
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
                echo '<a href="' . $scripturl . '?mode=claninfo&amp;clan='
                    . (int)$topclan[0] . '">'.htmlspecialchars((string)$topclan[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</a>';
            }
            else
            {
                echo '-';
            }
                    ?></td>
                </tr>
<?php
        }
        $db->free_result($resultGames);
?>
                </table>

        </div><br /><br />
        <br />

<?php
        printSectionTitle('General Statistics');

        // Build safe string for IN clause
        $nonhiddengamestring = (!empty($nonhiddengamecodes)) ? '(' . implode(',', $nonhiddengamecodes) . ')' : "('')";

        $res1 = $db->query("SELECT COUNT(playerId) FROM hlstats_Players WHERE game IN $nonhiddengamestring");
        $row = $db->fetch_row($res1);
        $num_players = number_format((int)($row[0] ?? 0));
        $db->free_result($res1);

        $res2 = $db->query("SELECT COUNT(clanId) FROM hlstats_Clans WHERE game IN $nonhiddengamestring");
        $row = $db->fetch_row($res2);
        $num_clans = number_format((int)($row[0] ?? 0));
        $db->free_result($res2);

        $res3 = $db->query("SELECT COUNT(serverId) FROM hlstats_Servers WHERE game IN $nonhiddengamestring");
        $row = $db->fetch_row($res3);
        $num_servers = number_format((int)($row[0] ?? 0));
        $db->free_result($res3);

        $res4 = $db->query("SELECT SUM(kills) FROM hlstats_Servers WHERE game IN $nonhiddengamestring");
        $row = $db->fetch_row($res4);
        $num_kills = number_format((int)($row[0] ?? 0));
        $db->free_result($res4);

        $res5 = $db->query("
            SELECT
                eventTime
            FROM
                hlstats_Events_Frags
            ORDER BY
                id DESC
            LIMIT 1
        ");
        $row = $db->fetch_row($res5);
        $lastevent = $row ? $row[0] : null;
        $db->free_result($res5);
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
            $time_val = strtotime((string)$lastevent);
            if ($time_val !== false) {
                echo "\t\t\t\t<li>Last Kill <strong> " . date('g:i:s A, D. M. d, Y', $time_val) . "</strong></li>";
            }
        }
?>
                <li>All statistics are generated in real-time. Event history data expires after <strong><?php echo $deletedays; ?></strong> days.</li>
            </ul>
        </div>
<?php
    }
?>