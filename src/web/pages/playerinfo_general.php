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
    $player = (int)$player;
    $game = isset($game) ? (string)$game : '';
    $game_esc = $db->escape($game);
?>

    <?php printSectionTitle('Player Information'); ?>
    <div class="subblock">
        <div style="float:left;vertical-align:top;width:48.5%;">
            <table class="data-table">
            <tr class="data-table-head">
                    <td style="vertical-align:top;">Player Profile<br /></td>
                    <td style="text-align:center; vertical-align:middle;" rowspan="7" id="player_avatar">
                        <?php
                            $db->query
                            ("
                                SELECT
                                    hlstats_PlayerUniqueIds.uniqueId,
                                    CASE
                                        WHEN hlstats_PlayerUniqueIds.uniqueId LIKE '7656119%'
                                            THEN hlstats_PlayerUniqueIds.uniqueId
                                        WHEN hlstats_PlayerUniqueIds.uniqueId LIKE '%U:1:%'
                                            THEN CAST('76561197960265728' AS unsigned) + CAST(REPLACE(REPLACE(SUBSTRING_INDEX(hlstats_PlayerUniqueIds.uniqueId, ':', -1), ']', ''), '[', '') AS unsigned)
                                        WHEN hlstats_PlayerUniqueIds.uniqueId LIKE 'STEAM\_%'
                                            THEN CAST('76561197960265728' AS unsigned) + CAST(SUBSTRING_INDEX(hlstats_PlayerUniqueIds.uniqueId, ':', -1) AS unsigned) * 2 + CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(hlstats_PlayerUniqueIds.uniqueId, ':', 2), ':', -1) AS unsigned)
                                        WHEN hlstats_PlayerUniqueIds.uniqueId LIKE '%:%' AND hlstats_PlayerUniqueIds.uniqueId NOT LIKE '-%'
                                            THEN CAST(LEFT(hlstats_PlayerUniqueIds.uniqueId, 1) AS unsigned) + CAST('76561197960265728' AS unsigned) + CAST(MID(hlstats_PlayerUniqueIds.uniqueId, 3, 10) * 2 AS unsigned)
                                        ELSE '76561197960265728'
                                    END AS communityId
                                FROM
                                    hlstats_PlayerUniqueIds
                                WHERE
                                    hlstats_PlayerUniqueIds.playerId = '$player'
                            ");

                            // PHP 8 Fix: Replace list()
                            $row = $db->fetch_row();
                            $uqid = ($row) ? $row[0] : '';
                            $coid = ($row) ? $row[1] : '';

                            $status = 'Unknown';
                            $avatar_full = IMAGE_PATH."/unknown.jpg";
                            $xml = false;

                            if ($coid !== '76561197960265728' && $coid != '') {

                                $profileUrl = "https://steamcommunity.com/profiles/" . urlencode((string)$coid) . "?xml=1";

                                $curl = curl_init();
                                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($curl, CURLOPT_URL, $profileUrl);
                                curl_setopt($curl, CURLOPT_ENCODING, "");
                                curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0");
                                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                                // Optional: Set timeout to prevent page hang
                                curl_setopt($curl, CURLOPT_TIMEOUT, 3);

                                $xml = curl_exec($curl);
                                curl_close($curl);
                            }

                            $xmlDoc = null;
                            if ($xml) {
                                // PHP 8 Fix: Suppress warnings for invalid XML & protect against XXE
                                $xmlDoc = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
                            }

                            if ($xmlDoc) {
                                $status = ucwords((string)$xmlDoc->onlineState);
                                $avatar_full = (string)$xmlDoc->avatarFull;
                            }

                            echo('<img src="' . htmlspecialchars((string)$avatar_full, ENT_QUOTES, 'UTF-8') . '" style="height:158px;width:158px;" alt="Steam Community Avatar" />');
                        ?>
                    </td>
                </tr>
                <tr class="bg2">
                    <td>
                        <?php
                            echo '<img src="'.getFlag($playerdata['flag']).'" alt="'.htmlspecialchars((string)$playerdata['country'], ENT_QUOTES, 'UTF-8').'" title="'.htmlspecialchars((string)$playerdata['country'], ENT_QUOTES, 'UTF-8').'" />&nbsp;';
                            echo '<strong>' . htmlspecialchars((string)$playerdata['lastName'], ENT_QUOTES, 'UTF-8') . ' </strong>';
                        ?>
                    </td>
                </tr>
                <tr class="bg1">
                    <td>
                        <?php
                            if ($playerdata['country'])
                            {
                                echo 'Location: ';
                                if ($playerdata['city']) {
                                    echo htmlspecialchars((string)$playerdata['city'], ENT_QUOTES, 'UTF-8') . ', ';
                                }
                                echo '<a href="'.htmlspecialchars((string)$g_options['scripturl'], ENT_QUOTES, 'UTF-8').'?mode=countryclansinfo&amp;flag='.htmlspecialchars((string)$playerdata['flag'], ENT_QUOTES, 'UTF-8').'&amp;game=' . htmlspecialchars($game, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string)$playerdata['country'], ENT_QUOTES, 'UTF-8') . '</a>';
                            }
                            else
                            {
                                echo 'Location: (Unknown)';
                            }
                        ?>
                    </td>
                </tr>
                <tr class="bg2">
                    <td>
                        <?php
                            $prefix = ((!preg_match('/^BOT/i',$uqid)) && ($g_options['Mode'] ?? '') == 'Normal') ? 'STEAM_0:' : '';
                            echo "Steam: <a href=\"https://steamcommunity.com/profiles/" . htmlspecialchars((string)$coid, ENT_QUOTES, 'UTF-8') . "\" target=\"_blank\" rel=\"noopener noreferrer\">$prefix" . htmlspecialchars((string)$uqid, ENT_QUOTES, 'UTF-8') . "</a>";
                        ?>
                    </td>
                </tr>
                <tr class="bg1">
                    <td>Status: <strong><?php echo htmlspecialchars((string)$status, ENT_QUOTES, 'UTF-8'); ?></strong></td>
                </tr>
                <tr class="bg2">
                    <td>
                        <a href="steam://friends/add/<?php echo htmlspecialchars((string)$coid, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Click here to add as friend</a>
                    </td>
                </tr>
                <tr class="bg1">
                    <td><?php echo "Karma: " . (isset($statusmsg) ? $statusmsg : 'Neutral'); ?></td>
                </tr>
                <tr class="bg2">
                    <td style="width:50%;">Member of Clan:</td>
                    <td style="width:50%;">
                        <?php
                            if ($playerdata['clan'])
                            {
                                echo '&nbsp;<a href="' . htmlspecialchars((string)$g_options['scripturl'], ENT_QUOTES, 'UTF-8') . '?mode=claninfo&amp;clan=' . (int)$playerdata['clan'] . '">' . htmlspecialchars((string)$playerdata['clan_name'], ENT_QUOTES, 'UTF-8') . '</a>';
                            }
                            else
                                echo '(None)';
                        ?>
                    </td>
                </tr>
                <tr class="bg1">
                    <td>Real Name:</td>
                    <td>
                        <?php
                            if ($playerdata['fullName'])
                            {
                                echo '<b>' . htmlspecialchars((string)$playerdata['fullName'], ENT_QUOTES, 'UTF-8') . '</b>';
                            }
                            else
                                echo "(<a href=\"" . htmlspecialchars((string)$g_options['scripturl'], ENT_QUOTES, 'UTF-8') . '?mode=help#set"><em>Not Specified</em></a>)';
                        ?>
                    </td>
                </tr>
                <tr class="bg2">
                    <td>E-mail Address:</td>
                    <td>
                        <?php
                            if ($playerdata['email'])
                            {
                                $safe_email = htmlspecialchars((string)$playerdata['email'], ENT_QUOTES, 'UTF-8');
                                echo "<a href=\"mailto:$safe_email\">$safe_email</a>";
                            }
                            else
                                echo "(<a href=\"" . htmlspecialchars((string)$g_options['scripturl'], ENT_QUOTES, 'UTF-8') . '?mode=help#set"><em>Not Specified</em></a>)';
                        ?>
                    </td>
                </tr>
                <tr class="bg1">
                    <td>Home Page:</td>
                    <td>
                        <?php
                            if ($playerdata['homepage'] && preg_match('/^https?:\/\//i', (string)$playerdata['homepage']))
                            {
                                $safe_url = htmlspecialchars((string)$playerdata['homepage'], ENT_QUOTES, 'UTF-8');
                                echo "<a href=\"$safe_url\" target=\"_blank\" rel=\"nofollow noopener noreferrer\">$safe_url</a>";
                            }
                            else
                                echo "(<a href=\"" . htmlspecialchars((string)$g_options['scripturl'], ENT_QUOTES, 'UTF-8') . '?mode=help#set"><em>Not Specified</em></a>)';
                        ?>
                    </td>
                </tr>
                <tr class="bg2">
                    <td>MM Rank:</td>
                    <td>
                        <?php
                            if ($playerdata['mmrank'])
                            {
                                echo '<img src="hlstatsimg/mmranks/' . (int)$playerdata['mmrank'] . '.png" alt="rank" style="height:20px;width:50px;" />';
                            }
                            else
                                echo '<img src="hlstatsimg/mmranks/0.png" alt="rank" style="height:20px;width:50px;" />';
                        ?>
                    </td>
                </tr>
                <tr class="bg1">
                    <td>First Connect:</td>
                    <td>
                        <?php
                            $db->query
                            ("
                                SELECT
                                    DATE_FORMAT(MIN(eventTime), '%a. %b. %D, %Y @ %T')
                                FROM
                                    hlstats_Events_Connects
                                WHERE
                                    playerId = '$player'
                                    AND eventTime IS NOT NULL
                            ");
                            $row = $db->fetch_row();
                            $firstevent = ($row) ? $row[0] : null;

                            if ($firstevent)
                                echo htmlspecialchars((string)$firstevent, ENT_QUOTES, 'UTF-8');
                            else
                                echo '(Unknown)';
                        ?>
                    </td>
                </tr>
                <tr class="bg2">
                    <td>Last Connect:</td>
                    <td>
                        <?php
                            $db->query
                            ("
                                SELECT
                                    DATE_FORMAT(eventTime, '%a. %b. %D, %Y @ %T')
                                FROM
                                    hlstats_Events_Connects
                                WHERE
                                    hlstats_Events_Connects.playerId = '$player'
                                ORDER BY
                                    id desc
                                LIMIT
                                    1
                            ");
                            // PHP 8 Fix: Replace list()
                            $row = $db->fetch_row();
                            $lastevent = ($row) ? $row[0] : null;

                            if ($lastevent)
                                echo htmlspecialchars((string)$lastevent, ENT_QUOTES, 'UTF-8');
                            else
                                echo '(Unknown)';
                        ?>
                    </td>
                </tr>
                <tr class="bg1">
                    <td>Total Connection Time:</td>
                    <td>
                        <?php echo timestamp_to_str((int)$playerdata['connection_time']); ?>
                    </td>
                </tr>
                <tr class="bg2">
                    <td>Average Ping:*</td>
                    <td>
                        <?php
                            $db->query
                            ("
                                SELECT
                                    ROUND(SUM(hlstats_Events_Latency.ping) / COUNT(hlstats_Events_Latency.ping), 0) AS av_ping,
                                    ROUND(ROUND(SUM(hlstats_Events_Latency.ping) / COUNT(ping), 0) / 2, 0) AS av_latency
                                FROM
                                    hlstats_Events_Latency
                                WHERE
                                    hlstats_Events_Latency.playerId = '$player'
                            ");
                            // PHP 8 Fix: Replace list()
                            $row = $db->fetch_row();
                            $av_ping = ($row && $row[0] !== null) ? (int)$row[0] : null;
                            $av_latency = ($row && $row[1] !== null) ? (int)$row[1] : null;

                            if ($av_ping !== null)
                                echo htmlspecialchars((string)$av_ping, ENT_QUOTES, 'UTF-8')." ms (Latency: " . htmlspecialchars((string)$av_latency, ENT_QUOTES, 'UTF-8') . " ms)";
                            else
                                echo '-';
                        ?>
                    </td>
                </tr>
                <tr class="bg1">
                    <td>Favorite Server:*</td>
                    <td>
                        <?php
                            // leave this one
                            $db->query
                            ("
                                SELECT
                                    hlstats_Events_Entries.serverId,
                                    hlstats_Servers.name,
                                    COUNT(hlstats_Events_Entries.serverId) AS cnt
                                FROM
                                    hlstats_Events_Entries
                                INNER JOIN
                                    hlstats_Servers
                                ON
                                    hlstats_Servers.serverId = hlstats_Events_Entries.serverId
                                WHERE
                                    hlstats_Events_Entries.playerId = '$player'
                                GROUP BY
                                    hlstats_Events_Entries.serverId
                                ORDER BY
                                    cnt DESC
                                LIMIT
                                    1
                            ");

                            // PHP 8 Fix: Replace list()
                            $row = $db->fetch_row();
                            if ($row) {
                                $favServerId = (int)$row[0];
                                $favServerName = htmlspecialchars((string)$row[1], ENT_QUOTES, 'UTF-8');
                                echo "<a href='hlstats.php?game=" . htmlspecialchars($game, ENT_QUOTES, 'UTF-8') . "&amp;mode=servers&amp;server_id=$favServerId'> $favServerName </a>";
                            } else {
                                echo '-';
                            }
                        ?>
                    </td>
                </tr>
                <tr class="bg2">
                    <td>Favorite Map:*</td>
                    <td>
                        <?php
                            $db->query
                            ("
                                SELECT
                                    hlstats_Events_Entries.map,
                                    COUNT(map) AS cnt
                                FROM
                                    hlstats_Events_Entries
                                WHERE
                                    hlstats_Events_Entries.playerId = '$player'
                                GROUP BY
                                    hlstats_Events_Entries.map
                                ORDER BY
                                    cnt DESC
                                LIMIT
                                    1
                            ");
                            // PHP 8 Fix: Replace list()
                            $row = $db->fetch_row();
                            $favMap = ($row) ? htmlspecialchars((string)$row[0], ENT_QUOTES, 'UTF-8') : null;
                            if ($favMap)
                                echo "<a href=\"hlstats.php?game=" . htmlspecialchars($game, ENT_QUOTES, 'UTF-8') . "&amp;mode=mapinfo&amp;map=$favMap\"> $favMap </a>";
                            else
                                echo '-';
                        ?>
                    </td>
                </tr>
                <tr class="bg1">
                    <td>Favorite Weapon:*</td>
                        <?php
                            $result = $db->query("
                                SELECT
                                    hlstats_Events_Frags.weapon,
                                    hlstats_Weapons.name,
                                    COUNT(hlstats_Events_Frags.weapon) AS kills,
                                    SUM(hlstats_Events_Frags.headshot=1) as headshots
                                FROM
                                    hlstats_Events_Frags
                                LEFT JOIN
                                    hlstats_Weapons
                                ON
                                    hlstats_Weapons.code = hlstats_Events_Frags.weapon
                                    AND hlstats_Weapons.game = '$game_esc'
                                WHERE
                                    hlstats_Events_Frags.killerId=$player
                                GROUP BY
                                    hlstats_Events_Frags.weapon,
                                    hlstats_Weapons.name
                                ORDER BY
                                    kills desc, headshots desc
                                LIMIT
                                    1
                            ");

                            $fav_weapon = '';
                            $weap_name = '';

                            while ($rowdata = $db->fetch_row($result)) {
                                $fav_weapon = $rowdata[0];
                                $weap_name = htmlspecialchars((string)$rowdata[1], ENT_QUOTES, 'UTF-8');
                            }

                            if ($fav_weapon == '') {
                                $fav_weapon = 'Unknown';
                            }

                            $image = getImage("/games/$game/weapons/$fav_weapon");
                        // Check if image exists
                            $weaponlink = "<a href=\"hlstats.php?mode=weaponinfo&amp;weapon=" . urlencode((string)$fav_weapon) . "&amp;game=" . htmlspecialchars($game, ENT_QUOTES, 'UTF-8') . "\">";
                            if ($image)
                            {
                                $cellbody = "<td style=\"text-align: center;\">$weaponlink<img src=\"" . htmlspecialchars((string)$image['url'], ENT_QUOTES, 'UTF-8') . "\" alt=\"$weap_name\" title=\"$weap_name\" /></a></td>";
                            }
                            else
                            {
                                $cellbody = "<td>$weaponlink<strong>$weap_name</strong></a></td>";
                            }
                        //    $cellbody .= "</a>";
                            echo $cellbody;
                        ?>
                </tr>
            </table><br />
        </div>

        <div style="float:right;vertical-align:top;width:48.5%;">
            <table class="data-table">
                <tr class="data-table-head">
                    <td style="vertical-align:top;" colspan="3">Statistics Summary<br /></td>
                </tr>
                <tr class="bg1">
                    <td style="width:50%;">Activity:</td>
                    <td style="width:35%;">
                                    <meter min="0" max="100" low="25" high="50" optimum="75" value="<?php
                                        echo (float)$playerdata['activity'] ?>"></meter>
                    </td>
                    <td style="width:15%;"><?php echo htmlspecialchars((string)$playerdata['activity'], ENT_QUOTES, 'UTF-8').'%'; ?></td>
                </tr>
                <tr class="bg2">
                    <td>Points:</td>
                    <td style="width:55%;" colspan="2">
                        <?php
                            echo '<b>' . number_format((int)$playerdata['skill']) . '</b>';
                        ?>
                    </td>
                </tr>
                <tr class="bg1">
                    <td style="width:45%;">Rank:</td>
                    <td style="width:55%;" colspan="2">
                        <?php
                            if (($playerdata['activity'] > 0) && ($playerdata['hideranking'] == 0))
                            {
                                $rank = get_player_rank($playerdata);
                            }
                            else
                            {
                                if ($playerdata['hideranking'] == 1)
                                {
                                    $rank = "Hidden";
                                }
                                elseif ($playerdata['hideranking'] == 2)
                                {
                                    $rank = "<span style=\"color:red;\">Banned</span>";
                                }
                                else
                                {
                                    $rank = 'Not active';
                                }
                            }
                            if (is_numeric($rank))
                            {
                                echo '<b>' . number_format((float)$rank) . '</b>';
                            }
                            else
                            {
                                echo "<b> $rank</b>";
                            }
                        ?>
                    </td>
                </tr>
                <tr class="bg2">
                    <td style="width:45%;">Kills per Minute:</td>
                    <td style="width:55%;" colspan="2">
                        <?php
                            if ($playerdata['connection_time'] > 0)
                            {
                                echo sprintf('%.2f', ($playerdata['kills'] / ($playerdata['connection_time'] / 60)));
                            }
                            else
                            {
                                echo '-';
                            }
                        ?>
                    </td>
                </tr>
                <tr class="bg1">
                    <td style="width:45%;">Kills per Death:</td>
                    <td style="width:55%;" colspan="2">
                        <?php
                            $db->query
                            ("
                                SELECT
                                    IFNULL(ROUND(SUM(hlstats_Events_Frags.killerId = '$player') / IF(SUM(hlstats_Events_Frags.victimId = '$player') = 0, 1, SUM(hlstats_Events_Frags.victimId = '$player')), 2), '-')
                                FROM
                                    hlstats_Events_Frags
                                WHERE
                                    (
                                        hlstats_Events_Frags.killerId = '$player'
                                        OR hlstats_Events_Frags.victimId = '$player'
                                    )
                            ");
                            // PHP 8 Fix: Replace list()
                            $row = $db->fetch_row();
                            $realkpd = ($row) ? $row[0] : '-';
                            echo htmlspecialchars((string)$playerdata['kpd'], ENT_QUOTES, 'UTF-8');
                            echo " (" . htmlspecialchars((string)$realkpd, ENT_QUOTES, 'UTF-8') . "*)";
                        ?>
                    </td>
                </tr>
                <tr class="bg2">
                    <td style="width:45%;">Headshots per Kill:</td>
                    <td style="width:55%;" colspan="2">
                        <?php
                            $db->query
                            ("
                                SELECT
                                    IFNULL(SUM(hlstats_Events_Frags.headshot=1) / COUNT(*), '-')
                                FROM
                                    hlstats_Events_Frags
                                WHERE
                                    hlstats_Events_Frags.killerId = '$player'
                            ");
                            // PHP 8 Fix: Replace list()
                            $row = $db->fetch_row();
                            $realhpk = ($row) ? $row[0] : '-';
                            echo htmlspecialchars((string)$playerdata['hpk'], ENT_QUOTES, 'UTF-8');
                            echo " (" . htmlspecialchars((string)$realhpk, ENT_QUOTES, 'UTF-8') . "*)";
                        ?>
                    </td>
                </tr>
                <tr class="bg1">
                    <td style="width:45%;">Shots per Kill:</td>
                    <td style="width:55%;" colspan="2">
                        <?php
                            $db->query
                            ("
                                SELECT
                                    IFNULL(ROUND((SUM(hlstats_Events_Statsme.hits) / SUM(hlstats_Events_Statsme.shots) * 100), 2), 0.0) AS accuracy,
                                    SUM(hlstats_Events_Statsme.shots) AS shots,
                                    SUM(hlstats_Events_Statsme.hits) AS hits,
                                    SUM(hlstats_Events_Statsme.kills) AS kills
                                FROM
                                    hlstats_Events_Statsme
                                WHERE
                                    hlstats_Events_Statsme.playerId='$player'
                            ");
                            // PHP 8 Fix: Replace list()
                            $row = $db->fetch_row();
                            $playerdata['accuracy'] = ($row) ? $row[0] : 0.0;
                            $sm_shots = ($row) ? $row[1] : 0;
                            $sm_hits = ($row) ? $row[2] : 0;
                            $sm_kills = ($row) ? $row[3] : 0;

                            if ($sm_kills > 0)
                            {
                                echo sprintf('%.2f', ($sm_shots / $sm_kills));
                            }
                            else
                            {
                                echo '-';
                            }
                        ?>
                    </td>
                </tr>
                <tr class="bg2">
                    <td style="width:45%;">Weapon Accuracy:</td>
                    <td style="width:55%;" colspan="2">
                        <?php
                            echo htmlspecialchars((string)$playerdata['acc'], ENT_QUOTES, 'UTF-8') . '%';
                            echo " (".sprintf('%.0f', (float)$playerdata['accuracy']).'%*)';
                        ?>
                    </td>
                </tr>
                <tr class="bg1">
                    <td style="width:45%;">Headshots:</td>
                    <td style="width:55%;" colspan="2">
                        <?php
                            if ($playerdata['headshots'] == 0) {
                                echo number_format((int)$realheadshots);
                            } else {
                                echo number_format((int)$playerdata['headshots']);
                                echo ' (' . number_format((int)$realheadshots) . '*)';
                            }
                        ?>
                    </td>
                </tr>
                <tr class="bg2">
                    <td style="width:45%;">Kills:</td>
                    <td style="width:55%;" colspan="2">
                        <?php
                            echo number_format((int)$playerdata['kills']);
                            echo ' ('.number_format((int)$realkills).'*)';
                        ?>
                    </td>
                </tr>
                <tr class="bg1">
                    <td style="width:45%;">Deaths:</td>
                    <td style="width:55%;" colspan="2">
                        <?php
                            echo number_format((int)$playerdata['deaths']);
                            echo ' ('.number_format((int)$realdeaths).'*)';
                        ?>
                    </td>
                </tr>
                <tr class="bg2">
                    <td style="width:45%;">Longest Kill Streak:</td>
                    <td style="width:55%;" colspan="2">
                        <?php
                            $db->query
                            ("
                                SELECT
                                    hlstats_Players.kill_streak
                                FROM
                                    hlstats_Players
                                WHERE
                                    hlstats_Players.playerId = '$player'
                            ");
                            // PHP 8 Fix: Replace list()
                            $row = $db->fetch_row();
                            $kill_streak = ($row) ? (int)$row[0] : 0;
                            echo number_format($kill_streak);
                        ?>
                    </td>
                </tr>
                <tr class="bg1">
                    <td style="width:45%;">Longest Death Streak:</td>
                    <td style="width:55%;" colspan="2">
                        <?php
                            $db->query
                            ("
                                SELECT
                                    hlstats_Players.death_streak
                                FROM
                                    hlstats_Players
                                WHERE
                                    hlstats_Players.playerId = '$player'
                            ");
                            // PHP 8 Fix: Replace list()
                            $row = $db->fetch_row();
                            $death_streak = ($row) ? (int)$row[0] : 0;
                            echo number_format($death_streak);
                        ?>
                    </td>
                </tr>
                <tr class="bg2">
                    <td style="width:45%;">Suicides:</td>
                    <td style="width:55%;" colspan="2">
                        <?php echo number_format((int)$playerdata['suicides']); ?>
                    </td>
                </tr>
                <tr class="bg1">
                    <td style="width:45%;">Teammate Kills:</td>
                    <td style="width:55%;" colspan="2">
                        <?php
                            echo number_format((int)$playerdata['teamkills']);
                            echo ' ('.number_format((int)$realteamkills).'*)';
                        ?>
                    </td>
                </tr>
            </table><br />
            <?php
                echo '&nbsp;&nbsp;<img src="' . htmlspecialchars((string)IMAGE_PATH, ENT_QUOTES, 'UTF-8') . '/history.gif" style="padding-left:3px;padding-right:3px;" alt="History" />&nbsp;<b>'
                    . htmlspecialchars((string)$playerdata['lastName'], ENT_QUOTES, 'UTF-8') . '</b>\'s History:<br />';
                echo '&nbsp;&nbsp;<a href="' . htmlspecialchars((string)$g_options['scripturl'], ENT_QUOTES, 'UTF-8') . "?mode=playerhistory&amp;player=$player\">Events</a>&nbsp;|&nbsp;";
                echo '<a href="' . htmlspecialchars((string)$g_options['scripturl'], ENT_QUOTES, 'UTF-8') . "?mode=playersessions&amp;player=$player\">Sessions</a>&nbsp;|&nbsp;";
                $resultCount = $db->query
                ("
                    SELECT
                        COUNT(*)
                    FROM
                        hlstats_Players_Awards
                    WHERE
                        hlstats_Players_Awards.playerId = $player
                ");
                // PHP 8 Fix: Replace list()
                $row = $db->fetch_row($resultCount);
                $numawards = ($row) ? (int)$row[0] : 0;
                $db->free_result($resultCount);

                echo "<a href=\"" . htmlspecialchars((string)$g_options['scripturl'], ENT_QUOTES, 'UTF-8') . "?mode=playerawards&amp;player=$player\">Awards&nbsp;($numawards)</a>&nbsp;|&nbsp;";
                if (($g_options["nav_globalchat"] ?? 0) == 1)
                {
                    echo "<a href=\"" . htmlspecialchars((string)$g_options['scripturl'], ENT_QUOTES, 'UTF-8') . "?mode=chathistory&amp;player=$player\">Chat</a>";
                }
            ?>
            <br />&nbsp;&nbsp;<a href="<?php echo htmlspecialchars((string)$g_options['scripturl'], ENT_QUOTES, 'UTF-8'); ?>?mode=search&amp;st=player&amp;q=<?php echo isset($pl_urlname) ? htmlspecialchars((string)$pl_urlname, ENT_QUOTES, 'UTF-8') : rawurlencode((string)$playerdata['lastName']); ?>"><img src="<?php echo htmlspecialchars((string)IMAGE_PATH, ENT_QUOTES, 'UTF-8'); ?>/search.gif" style="margin-left:3px;margin-right:3px;" alt="Search" />&nbsp;Find other players with the same name</a>
        </div>
    </div>
    <br /><br />
    <div style="clear:both;padding-top:24px;"></div>
    <?php printSectionTitle('Miscellaneous Statistics'); ?>
    <div class="subblock">
        <div style="float:left;vertical-align:top;width:48.5%;">
            <table class="data-table">
                <tr class="data-table-head">
                    <td>Player Trend</td>
                </tr>
                <tr class="bg1">
                    <td style="text-align:center;">
                        <?php echo "<img src=\"trend_graph.php?bgcolor=".htmlspecialchars((string)($g_options['graphbg_trend'] ?? '282828'), ENT_QUOTES, 'UTF-8').'&amp;color='.htmlspecialchars((string)($g_options['graphtxt_trend'] ?? 'FFFFFF'), ENT_QUOTES, 'UTF-8')."&amp;player=$player\" alt=\"Player Trend Graph\" />"; ?>
                    </td>
                </tr>
            </table>
        </div>
        <div style="float:right;vertical-align:top;width:48.5%;">
            <table class="data-table">
                <tr class="data-table-head">
                    <td>Forum Signature</td>
                </tr>
                <tr class="bg1">
                    <td style="text-align:center;">
                        <br /><br />
                        <?php
                            $protocol = (isset($_SERVER['SSL']) || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')) ? 'https://' : 'http://';
                            $script_path = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '')), '/');

                            if (($g_options['modrewrite'] ?? 0) == 0)
                            {
                                $imglink = "sig.php?player_id=$player&amp;background=" . htmlspecialchars((string)($g_options['sigbackground'] ?? 'random'), ENT_QUOTES, 'UTF-8');
                                $jimglink = $script_path . "/sig.php?player_id=$player&background=" . ($g_options['sigbackground'] ?? 'random');
                                $forum_imglink = $script_path . "/sig.php?player_id=$player&amp;background=" . htmlspecialchars((string)($g_options['sigbackground'] ?? 'random'), ENT_QUOTES, 'UTF-8');
                            }
                            else
                            {
                                $imglink = "sig-$player-" . htmlspecialchars((string)($g_options['sigbackground'] ?? 'random'), ENT_QUOTES, 'UTF-8') . ".png";
                                $jimglink = $script_path . "/sig-$player-" . ($g_options['sigbackground'] ?? 'random') . ".png";
                                $forum_imglink = $jimglink;
                            }

                            echo "<img src=\"$imglink\" title=\"Copy &amp; Paste the whole URL below in your forum signature\" alt=\"forum sig image\"/>";
                        ?>
                        <br /><br />
                        <script type="text/javascript">
                            /* <![CDATA[ */
                            function setForumText(val)
                            {
                                var txtArea = document.getElementById('siglink');
                                switch(val)
                                {
                                    case 0:
                                        <?php echo "txtArea.value = '" . addcslashes($jimglink, "'\\\"\r\n") . "';\n"; ?>
                                        break;
                                    case 1:
                                        <?php echo "txtArea.value = '[url=" . addcslashes($script_path, "'\\\"\r\n") . "/hlstats.php?mode=playerinfo&game=" . addcslashes($game, "'\\\"\r\n") . "&player=$player][img]" . addcslashes($jimglink, "'\\\"\r\n") . "[/img][/url]';\n"; ?>
                                        break;
                                    case 2:
                                        <?php echo "txtArea.value = '[url=\"" . addcslashes($script_path, "'\\\"\r\n") . "/hlstats.php?mode=playerinfo&game=" . addcslashes($game, "'\\\"\r\n") . "&player=$player\"][img]" . addcslashes($jimglink, "'\\\"\r\n") . "[/img][/url]';\n"; ?>
                                        break;
                                }
                            }
                            /* ]]> */
                        </script>
                        <a href="" onclick="setForumText(1);return false">
                            bbCode 1 (phpBB, SMF)</a>&nbsp;|&nbsp;<a href="" onclick="setForumText(2);return false">bbCode 2 (IPB)</a>&nbsp;|&nbsp;<a href="" onclick="setForumText(0);return false">Direct Image
                        </a>
                        <?php echo '<textarea style="width: 95%; height: 50px;" rows="2" cols="70" id="siglink" readonly="readonly" onclick="document.getElementById(\'siglink\').select();">[url='."$script_path/hlstats.php?mode=playerinfo&amp;player=$player"."][img]$forum_imglink".'[/img][/url]</textarea>'; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    <br /><br />
<?php
// Current rank & rank history
    $db->query
    ("
        SELECT
            hlstats_Ranks.rankName,
            hlstats_Ranks.image,
            hlstats_Ranks.minKills
        FROM
            hlstats_Ranks
        WHERE
            hlstats_Ranks.minKills <= ".(int)$playerdata['kills']."
            AND hlstats_Ranks.game = '$game_esc'
        ORDER BY
            hlstats_Ranks.minKills DESC
        LIMIT
            1
    ");
    $result = $db->fetch_array();
    $rankimage = getImage('/ranks/' . ($result['image'] ?? ''));
    $rankName = (string)($result['rankName'] ?? '');
    $rankCurMinKills = (int)($result['minKills'] ?? 0);
    $db->query
    ("
        SELECT
            hlstats_Ranks.rankName,
            hlstats_Ranks.minKills
        FROM
            hlstats_Ranks
        WHERE
            hlstats_Ranks.minKills > ".(int)$playerdata['kills']."
            AND hlstats_Ranks.game = '$game_esc'
        ORDER BY
            hlstats_Ranks.minKills
        LIMIT
            1
    ");
    if ($db->num_rows() == 0)
    {
        $rankKillsNeeded = 0;
        $rankPercent = 0;
    }
    else
    {
        $result = $db->fetch_array();
        $rankKillsNeeded = (int)$result['minKills'] - (int)$playerdata['kills'];
        $denom = (int)$result['minKills'] - $rankCurMinKills;
        $rankPercent = ($denom > 0) ? (((int)$playerdata['kills'] - $rankCurMinKills) * 100 / $denom) : 0;
    }
    $db->query
    ("
        SELECT
            hlstats_Ranks.rankName,
            hlstats_Ranks.image
        FROM
            hlstats_Ranks
        WHERE
            hlstats_Ranks.minKills <= ".(int)$playerdata['kills']."
            AND hlstats_Ranks.game = '$game_esc'
        ORDER BY
            hlstats_Ranks.minKills
    ");

    $rankHistory = "";
    $db_num_rows = $db->num_rows();

    for ($i = 1; $i < $db_num_rows; $i++) {
        $result = $db->fetch_array();

        $histimage = getImage('/ranks/' . ($result['image'] ?? '') . '_small');
        $histurl = is_array($histimage) ? ($histimage['url'] ?? '') : '';
        $rankHistory .= '<img src="' . htmlspecialchars((string)$histurl, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars((string)($result['rankName'] ?? ''), ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars((string)($result['rankName'] ?? ''), ENT_QUOTES, 'UTF-8') . '" /> ';
    }
?>

    <div style="clear:both;padding-top:24px;"></div>
    <?php printSectionTitle('Ranks'); ?>
    <div class="subblock">
        <div style="float:left;vertical-align:top;width:48.5%;">
            <table class="data-table">
                <tr class="data-table-head">
                    <td colspan="2">
                        Current rank: <b><?php echo htmlspecialchars((string)$rankName, ENT_QUOTES, 'UTF-8'); ?></b>
                    </td>
                </tr>
                <tr class="bg1">
                    <td style="text-align:center;" colspan="2">
                        <?php echo '<img src="' . htmlspecialchars((string)($rankimage['url'] ?? ''), ENT_QUOTES, 'UTF-8') . "\" alt=\"".htmlspecialchars((string)$rankName, ENT_QUOTES, 'UTF-8')."\" title=\"".htmlspecialchars((string)$rankName, ENT_QUOTES, 'UTF-8')."\" />"; ?>
                    </td>
                </tr>
                <tr class="data-table-head">
                    <td style="width:60%;">
                        <meter min="0" max="100" low="25" high="50" optimum="75" value="<?php
                                        echo (float)$rankPercent ?>"></meter>

                    </td>
                    <td style="width:40%;">
                        Kills needed: <b><?php echo "$rankKillsNeeded (".number_format((float)$rankPercent, 0, '.', '');?>%)</b>
                    </td>
                </tr>
            </table>
        </div>
        <div style="float:right;vertical-align:top;width:48.5%;">
            <table class="data-table">
                <tr class="data-table-head">
                    <td>Rank history</td>
                </tr>
                <tr class="bg1">
                    <td style="text-align:center;"><?php echo $rankHistory; ?></td>
                </tr>
            </table>
        </div>
    </div>
    <br /><br />

<?php
// Awards
    $res = $db->query
    ("
        SELECT
            hlstats_Ribbons.awardCode AS ribbonCode,
            hlstats_Ribbons.ribbonName AS ribbonName,
            IF(ISNULL(hlstats_Players_Ribbons.playerId), 'noaward.png', hlstats_Ribbons.image) AS image,
            hlstats_Ribbons.special,
            hlstats_Ribbons.image AS imagefile,
            hlstats_Ribbons.awardCount
        FROM
            hlstats_Ribbons
        LEFT JOIN
        (
            SELECT
                hlstats_Players_Ribbons.playerId,
                hlstats_Ribbons.awardCode,
                hlstats_Players_Ribbons.ribbonId
            FROM
                hlstats_Players_Ribbons
            INNER JOIN
                hlstats_Ribbons
            ON
                hlstats_Ribbons.ribbonId = hlstats_Players_Ribbons.ribbonId
                AND hlstats_Ribbons.game = hlstats_Players_Ribbons.game
            WHERE
                hlstats_Players_Ribbons.playerId = ".(int)$playerdata['playerId']."
                AND hlstats_Players_Ribbons.game = '$game_esc'
            ORDER BY
                hlstats_Ribbons.awardCount DESC
        ) AS hlstats_Players_Ribbons
        ON
            hlstats_Players_Ribbons.ribbonId = hlstats_Ribbons.ribbonId
        WHERE
            hlstats_Ribbons.game = '$game_esc'
            AND
            (
                ISNULL(hlstats_Players_Ribbons.playerId)
                OR hlstats_Players_Ribbons.playerId = ".(int)$playerdata['playerId']."
            )
        ORDER BY
            hlstats_Ribbons.awardCode,
            hlstats_Players_Ribbons.playerId DESC,
            hlstats_Ribbons.special,
            hlstats_Ribbons.awardCount DESC
    ");
    $ribbonList = '';
    $lastImage = '';
    $awards_done = array ();
    while ($result = $db->fetch_array($res))
    {
        $ribbonCode=$result['ribbonCode'];
        $ribbonName=$result['ribbonName'];
        if(!isset($awards_done[$ribbonCode]))
        {
            if (file_exists(IMAGE_PATH."/games/$game/ribbons/".$result['image']))
            {
                $image = IMAGE_PATH."/games/$game/ribbons/".$result['image'];
            }
            elseif (!empty($realgame) && file_exists(IMAGE_PATH."/games/$realgame/ribbons/".$result['image']))
            {
                $image = IMAGE_PATH."/games/$realgame/ribbons/".$result['image'];
            }
            else
            {
                $image = IMAGE_PATH."/award.png";
            }
            $ribbonList .= '<img src="'.htmlspecialchars((string)$image, ENT_QUOTES, 'UTF-8').'" style="border:0px;" alt="'.htmlspecialchars((string)$result['ribbonName'], ENT_QUOTES, 'UTF-8').'" title="'.htmlspecialchars((string)$result["ribbonName"], ENT_QUOTES, 'UTF-8').'" /> ';
            $awards_done[$ribbonCode]=$ribbonCode;
        }
    }
    $awards = array ();
    $res_gawards = $db->query
    ("
    SELECT
        hlstats_Awards.awardType,
        hlstats_Awards.code,
        hlstats_Awards.name
    FROM
        hlstats_Awards
    WHERE
        hlstats_Awards.game = '$game_esc'
        AND hlstats_Awards.g_winner_id = $player
    ORDER BY
        hlstats_Awards.name
    ");

    while ($r1 = $db->fetch_array($res_gawards)) {
        // PHP 8 Fix: No need to unset, just initialize object
        $tmp_arr = new stdClass();

        $tmp_arr->aType = $r1['awardType'];
        $tmp_arr->code = $r1['code'];
        $tmp_arr->ribbonName = $r1['name'];

        array_push($awards, $tmp_arr);
    }

    $GlobalAwardsList = '';
    foreach ($awards as $a)
    {
        if ($image = getImage("/games/$game/gawards/".strtolower($a->aType."_$a->code")))
        {
            $image = $image['url'];
        }
        elseif (!empty($realgame) && $image = getImage("/games/$realgame/gawards/".strtolower($a->aType."_$a->code")))
        {
            $image = $image['url'];
        }
        else
        {
            $image = IMAGE_PATH."/award.png";
        }
        $GlobalAwardsList .= "<img src=\"".htmlspecialchars((string)$image, ENT_QUOTES, 'UTF-8')."\" alt=\"".htmlspecialchars((string)$a->ribbonName, ENT_QUOTES, 'UTF-8')."\" title=\"".htmlspecialchars((string)$a->ribbonName, ENT_QUOTES, 'UTF-8')."\" /> ";
    }
    if ($ribbonList != '' || $GlobalAwardsList != '')
    {
?>

    <div style="clear:both;padding-top:24px;"></div>
    <?php printSectionTitle('Awards (hover over image to see name)'); ?>
    <div class="subblock">
        <div style="float:left;vertical-align:top;width:68.5%;">
            <table class="data-table">
                <tr class="data-table-head">
                    <td>Ribbons</td>
                </tr>
                <tr class="bg1">
                    <td style="text-align:center;"><?php echo $ribbonList; ?></td>
                </tr>
            </table>
        </div>
        <div style="float:right;vertical-align:top;width:28.5%;">
            <table class="data-table">
                <tr class="data-table-head">
                    <td>Global Awards</td>
                </tr>
                <tr class="bg1">
                    <td style="text-align:center;"><?php echo $GlobalAwardsList; ?></td>
                </tr>
            </table>
        </div>
    </div>
    <br /><br />
<?php
    }
?>